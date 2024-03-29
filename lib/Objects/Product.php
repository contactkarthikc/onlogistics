<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * This file is part of Onlogistics, a web based ERP and supply chain 
 * management application. 
 *
 * Copyright (C) 2003-2008 ATEOR
 *
 * This program is free software: you can redistribute it and/or modify it 
 * under the terms of the GNU Affero General Public License as published by 
 * the Free Software Foundation, either version 3 of the License, or (at your 
 * option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT 
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or 
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public 
 * License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5.1.0+
 *
 * @package   Onlogistics
 * @author    ATEOR dev team <dev@ateor.com>
 * @copyright 2003-2008 ATEOR <contact@ateor.com> 
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL
 * @version   SVN: $Id$
 * @link      http://www.onlogistics.org
 * @link      http://onlogistics.googlecode.com
 * @since     File available since release 0.1.0
 * @filesource
 */

class Product extends _Product {
    // Constructeur {{{

    /**
     * Product::__construct()
     * Constructeur
     *
     * @access public
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    // }}}
    // properties {{{

    // cache pour ne pas rechercher la promotion � chaque getter
    var $_promotion = false;

    // }}}
    // Product::getPriceByActor() {{{

    /**
     * Retourne le prix de l'UV dans la devise de l'acteur pass� en param�tre
     *
     * @access public
     * @param  $actor le client
     * @return float le prix dans la devise d�finie pour le client
     */
    public function getPriceByActor($actor=false)
    {
        if (false == $actor || !($actor instanceof Actor)) {
            // on ne connait pas l'acteur donc on prend l'acteur connect�
            $auth = Auth::singleton();
            $actor = $auth->getActor();
        }
        $currencyID = $actor->getCurrencyId();
        $zoneID     = $actor->getPricingZoneId();
        // on essaie d'abord de r�cup�rer le prix associ� � la devise *et* � la 
        // zone param�tr�e de l'acteur, s'il y en a une
        if ($zoneID > 0 && $currencyID > 0) {
            $pbc = Object::load('PriceByCurrency', array(
                'Product'     => $this->getId(),
                'Currency'    => $currencyID,
                'PricingZone' => $zoneID
            ));
            if ($pbc instanceof PriceByCurrency) {
                return $pbc->getPrice();
            }
            return 0;
        }
        // sinon, on essaie de r�cup�rer le prix associ� � la devise
        if ($currencyID > 0) {
            $pbc = Object::load('PriceByCurrency', array(
                'Product'  => $this->getId(),
                'Currency' => $currencyID
            ));
            if ($pbc instanceof PriceByCurrency) {
                return $pbc->getPrice();
            }
        }
        return 0;
    }

    // }}}
    // Product::getAllClassProperties() {{{

    /**
     *
     * @access public
     * @return void
     */
    public function getAllClassProperties($allProductTypes=false)
    {
        require_once('Objects/Property.inc.php');
        $properties = $this->getProperties();
        if ($allProductTypes) {
            $mapper = Mapper::singleton('ProductType');
            $types = $mapper->loadCollection();
            $count = $types->getCount();
            for ($i=0; $i<$count; $i++) {
                $type = $types->getItem($i);
                $this->_getPropertiesForProductType($type, $properties);
            }
        } else {
            $type = $this->getProductType();
            $this->_getPropertiesForProductType($type, $properties);
        }
        return $properties;
    }

    // }}}
    // Product::_getPropertiesForProductType() {{{

    /**
     * Compl�te le tableau $properties pass� par r�f�rence avec les propri�t�s
     * du type de produit $type.
     *
     * @access public
     * @param object ProductType $type
     * @param array $properties (tableau pass� par r�f�rence)
     * @return void
     */
    public function _getPropertiesForProductType($type, &$properties) {
        if (!($type instanceof ProductType)) {
            return;
        }
        $excludes = array('Supplier', 'SupplierReference', 'BuyUnitType');
        $dynProperties = $type->getPropertyArray();
        $simpletypes = array(Property::STRING_TYPE, Property::INT_TYPE, Property::FLOAT_TYPE, Property::DATE_TYPE);
        foreach($dynProperties as $propname => $prop) {
            if (in_array($propname, array_keys($properties)) ||
                in_array($propname, $excludes)) {
                continue;
            }
            $type = $prop->getType();
            if (in_array($type, $simpletypes)) {
                $properties[$propname] = $type;
            } else {
                $properties[$propname] = $propname;
            }
        }
    }

    // }}}
    // Product::_getChainCollection() {{{

    /**
     * Methode addon qui va chercher toutes les chaines
     * auxquelles le produit est affect�
     *
     * @access public
     * @return collection
     */
    public function getChainCollection()
    {
        $ProductChainLinkCollection = $this->GetProductChainLinkCollection();

        $ChainCollection = new Collection();
        if (!Tools::isEmptyObject($ProductChainLinkCollection)) {
            for($i = 0; $i < $ProductChainLinkCollection->GetCount(); $i++) {
                unset($ProductChainLink);
                $ProductChainLink = $ProductChainLinkCollection->GetItem($i);
                $ChainCollection->SetItem($ProductChainLink->GetChain());
            }
        }
        return $ChainCollection;
    }

    // }}}
    // Product::baseUnitCount() {{{

    public function baseUnitCount($quantity)
    {
        require_once('Objects/SellUnitType.const.php');
        $count = -1;
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case SELLUNITTYPE_UB:
                $count = $quantity;
                break;
            case SELLUNITTYPE_UC:
                $count = $quantity * $this->getUnitNumberInConditioning();
                break;
            case SELLUNITTYPE_UE:
                $count = $quantity * $this->getUnitNumberInPackaging();
                break;
            case SELLUNITTYPE_UR:
                $grcol = $this->getGroupingCollection();
                if (Tools::isEmptyObject($grcol) || ($grcol->getCount() == 0)) {
                    return new Exception(_('No regrouping unit type'));
                }
                $gr = $grcol->getItem(0);
                $count = $quantity * $this->getUnitNumberInPackaging() * $gr->getUnitNumberInGrouping();
                break;

            default:
                return $quantity;
        }
        return ceil($count);
    }

    // }}}
    // Product::conditioningUnitCount() {{{

    public function conditioningUnitCount($quantity)
    {
        require_once('Objects/SellUnitType.const.php');
        $count = -1;
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case SELLUNITTYPE_UB:
                $count = $quantity * $this->getSellUnitQuantity() / $this->getUnitNumberInConditioning();
                break;
            case SELLUNITTYPE_UC:
                $count = $quantity * $this->getSellUnitQuantity();
                break;
            case SELLUNITTYPE_UE:
                $count = $quantity * $this->getSellUnitQuantity()
                    * $this->getUnitNumberInPackaging() / $this->getUnitNumberInConditioning();
                break;
            case SELLUNITTYPE_UR:
                $grcol = $this->getGroupingCollection();
                if (Tools::isEmptyObject($grcol) || ($grcol->getCount() == 0)) {
                    return new Exception(_('No regrouping unit type'));
                }

                $gr = $grcol->getItem(0);
                $count = $quantity * $this->getSellUnitQuantity() * $this->getUnitNumberInPackaging()
                    * $gr->getUnitNumberInGrouping() / $this->getUnitNumberInConditioning();
                break;

            default:
                $count = $quantity * $this->getSellUnitQuantity();
        }
        return ceil($count);
    }

    // }}}
    // Product::packagingUnitCount() {{{

    /*
    * Methode AddOn : calcule le nbre d'UE necessaires pour 1 qte donnee de SellUnitType
    * @param $quantity integer nb de SellUnitType
    * @param $execution integer 0 par defaut, 1 si execution deconnectee
    **/
    public function packagingUnitCount($quantity, $execution = 0)
    {
        require_once('Objects/SellUnitType.const.php');
        $count = -1;
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case SELLUNITTYPE_UB:
                $count = $quantity * $this->getSellUnitQuantity();
                break;
            case SELLUNITTYPE_UC:
                $count = $quantity * $this->getSellUnitQuantity()
                    * $this->getUnitNumberInConditioning() / $this->getUnitNumberInPackaging();
                break;
            case SELLUNITTYPE_UE:
                $count = $quantity * $this->getSellUnitQuantity() / $this->getUnitNumberInPackaging();
                break;
            case SELLUNITTYPE_UR:
                $grcol = $this->getGroupingCollection();
                if (Tools::isEmptyObject($grcol) || ($grcol->GetCount() == 0)) {
                    return new Exception(_('No regrouping unit type'));
                }
                $gr = $grcol->getItem(0);
                $count = $quantity * $this->getSellUnitQuantity() / $gr->getUnitNumberInGrouping();
                break;
            default:
                $count = $quantity * $this->getSellUnitQuantity();
        }
        if ($execution == 1) {
            return $count;
        }
        return ceil($count);
    }

    // }}}
    // Product::groupingUnitCount() {{{

    public function groupingUnitCount($quantity)
    {
        $count = -1;
        $Packaging = $this->getPackagingRecommended();
        if (!Tools::isEmptyObject($Packaging)) {
            $gr = $Packaging->getGroupingRecommended();
        }
        if (!Tools::isEmptyObject($gr)) {
            require_once('Objects/SellUnitType.const.php');
            $sutId = $this->getSellUnitTypeId();
            switch ($sutId) {
            case SELLUNITTYPE_UB:
                    $count = $quantity * $this->getSellUnitQuantity()
                        / ($this->getUnitNumberInPackaging() * $gr->getUnitNumberInGrouping());
                    break;
                case SELLUNITTYPE_UC:
                    $count = $quantity * $this->getSellUnitQuantity()
                        * $this->getUnitNumberInConditioning()
                        / ($this->getUnitNumberInPackaging() * $gr->getUnitNumberInGrouping());
                    break;
                case SELLUNITTYPE_UE:
                    $count = $quantity * $this->getSellUnitQuantity()
                        / $gr->getUnitNumberInGrouping();
                    break;
                case SELLUNITTYPE_UR:
                    $count = $quantity * $this->getSellUnitQuantity();
                    break;
                default:
                    $count = $quantity * $this->getSellUnitQuantity();
            }
        }
        return ceil($count);
    }

    // }}}
    // Product::getPromotion() {{{

    /**
     * Retourne une Promotion s'il en existe une et false sinon
     *
     * @access public
     * @param object $customer Actor: Customer
     * @param string $date : date (par defaut, date courante
     * @return void
     */
    public function getPromotion($customer = false, $date = '')
    {
        if (!($customer instanceof Actor)) {
            return false;
        }
        if (false !== $this->_promotion) {
            return $this->_promotion;
        }

        $categoryId = $customer->GetCategoryId();
        $currencyID = $customer->GetCurrencyId();
        $date = ($date == '')?date('Y-m-d H-i-s'):$date;

        $filter = new FilterComponent();
        $filter->setItem(new FilterRule('EndDate',
                FilterRule::OPERATOR_GREATER_THAN_OR_EQUALS,
                $date));
        $filter->setItem(new FilterRule('StartDate',
                FilterRule::OPERATOR_LOWER_THAN,
                $date));
        $filter->setItem(new FilterRule('Currency',
                FilterRule::OPERATOR_EQUALS,
                $currencyID));
        $filter->operator = FilterComponent::OPERATOR_AND;

        $promoMapper = Mapper::singleton('Promotion');
        $promoCol = $promoMapper->loadCollection($filter,
            array('Id' => SORT_DESC));
        if (Tools::isEmptyObject($promoCol)) {
            return false;
        }
        for ($i = 0;$i < $promoCol->GetCount();$i++) {
            $promo = $promoCol->GetItem($i);
            if (in_array($categoryId, $promo->getCategoryCollectionIdsForPromotion()) &&
                in_array($this->getId(), $promo->GetProductCollectionIdsForPromotion())) {
                $this->_promotion = $promo;
                return $promo;
            }
        }
        return false;
    }

    // }}}
    // Product::getUnitHTForCustomerPrice() {{{

    /**
     * Methode addon qui calcule le prix d'un product en fonction du client
     * et de la promotion eventuellement liee au produit
     *
     * @param object $ Customer $customer
     * @param string $date: (par defaut, '')
     * @param object $Promotion : (par defaut, ''): si on connait la promo
     * @access public
     * @return float
     */
    public function getUnitHTForCustomerPrice($customer, $date = '')
    {
        require_once('FormatNumber.php');
        if (!($customer instanceof Customer) && !($customer instanceof AeroCustomer)) {
            return 'N/A';
        }

        $initPrice = $this->GetPriceByActor($customer); // prix de base
        // on r�cup�re la promotion du produit s'il y en a une
        $Promotion = $this->GetPromotion($customer, $date);
        if (!Tools::isEmptyObject($Promotion)) {
            require_once('Objects/Promotion.php');
            if (Promotion::PROMO_TYPE_MONTANT == $Promotion->GetType()) {
                // Reduction = montant
                $promoPrice = $initPrice - $Promotion->GetRate();
            } else {
                // Reduction = pourcentage
                $promoPrice = $initPrice -
                    (($initPrice * $Promotion->GetRate()) / 100);
            }
            return troncature($promoPrice);
        }
        // Pas de promo ni remise ds ce cas
        return $initPrice;
    }

    // }}}
    // Product::getUnitHTForCustomerPriceWithDiscount() {{{

    /**
     * Methode addon qui calcule le prix d'un product en fonction du client
     * de la promotion eventuellement liee au produit, de la remise par
     * cat�gorie et la remise exceptionnelle du client
     *
     * @param object $ Customer $customer
     * @param string $date : (par defaut, ''): si on ne sait pas s'il y a une promo
     * @param object $Promotion : (par defaut, ''): si on connait la promo
     * @access public
     * @return float
     */
    public function getUnitHTForCustomerPriceWithDiscount($customer, $date = '')
    {
        // On r�cup�re le prix exact du produit pour le client qui commande
        $unitHT = $this->getUnitHTForCustomerPrice($customer, $date);
        // On tient compte aussi de la Remise Exceptionnelle liee au Customer
        // et de l'�ventuelle remise par cat�gorie
        $catID = $customer->getCategoryId();
        if ($catID > 0) {
            // on recherche une remise �ventuelle
            require_once('SQLRequest.php');
            $rem = request_ProductHandingByCategory($this->getId(), $catID);
            if ($rem > 0) {
                $unitHT -= ($unitHT * ($rem / 100));
            }
        }
        $remExp = $customer->getRemExcep();
        if ($remExp > 0) {
            $unitHT -= ($unitHT * ($remExp / 100));
        }
        return $unitHT;
    }

    // }}}
    // Product::getRealQuantity() {{{

    /**
     * methode Addon : Calcule la Quantite reelle totale en stock
     *
     * @param int $StorageSiteId : facultatif, si on veut la Quantite pour un site donne
     * @param int $SitOwnerId : pour un proprietaire de site donne, oubien 0
     * @param int $lpqActivated : 0: tous, 1: que dans les LPQ et Location actives
     * @return int Content of value
     * @access public
     */
    public function getRealQuantity($StorageSiteId = 0, $SitOwnerId = 0, $lpqActivated = 0)
    {
        $LPQMapper = Mapper::singleton('LocationProductQuantities');
        $Filter = array('Product' => array($this->GetId()));
        if ($StorageSiteId > 0) {
            $Filter['Location.Store.StorageSite'] = array($StorageSiteId);
        }
        if ($SitOwnerId > 0) {
            $Filter['Location.Store.StorageSite.Owner'] = array($SitOwnerId);
        }
        if ($lpqActivated == 1) {
            $Filter['Activated'] = array(1);
            $Filter['Location.Activated'] = array(1);
        }
        $LPQCollection = $LPQMapper->loadCollection($Filter);

        $TotalRealQuantity = 0;
        for($i = 0; $i < $LPQCollection->getCount(); $i++) {
            $item = $LPQCollection->getItem($i);
            $TotalRealQuantity += $item->getRealQuantity();
            unset($item);
        }
        return $TotalRealQuantity;
    }

    // }}}
    // Product::getVirtualQuantity() {{{

    /**
     * methode Addon : Calcule la Quantite virtuelle totale
     * Methode SUPPRIMEE tant qu'on ne gere pas de virtualquantity par Location
     *
     * @return int Content of value
     * @access public
     */
    /*function GetVirtualQuantity(){

        $LocationProductQuantitiesMapper = Mapper::singleton('LocationProductQuantities');
        $LocationProductQuantitiesCollection = $LocationProductQuantitiesMapper->loadCollection(Array('Product' => $this->_Id));
        return($LocationProductQuantitiesCollection -> getTotalVirtualQuantity());
    }
    */

    // }}}
    // Product::getTotalQuantities() {{{

    /**
     * methode Addon : Calcule les quantites reelle et virtuelle totales
     * Methode SUPPRIMEE tant qu'on ne gere pas de virtualquantity par Location
     *
     * @return int Content of value
     * @access public
     */
    /*
    function GetTotalQuantities(){

        $LocationProductQuantitiesMapper = Mapper::singleton('LocationProductQuantities');
        $LocationProductQuantitiesCollection = $LocationProductQuantitiesMapper->loadCollection(Array('Product' => $this->_Id));
        return $LocationProductQuantitiesCollection -> getTotalQuantities();
    }
*/

    // }}}
    // Product::getNumberUBInUV() {{{

    public function getNumberUBInUV()
    {
        require_once('Objects/SellUnitType.const.php');
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case 'SELLUNITTYPE_NONE':
                return 'N/A';
                break;
            case SELLUNITTYPE_UB:
                return $this->getSellUnitQuantity();
                break;
            case SELLUNITTYPE_UC:
                return ($this->getSellUnitQuantity()
                        * $this->getUnitNumberInConditioning());
                break;
            case SELLUNITTYPE_UE:
                $sutInContainerId = $this->getSellUnitTypeInContainerId();
                if ($sutInContainerId == SELLUNITTYPE_UB) {
                    return($this->getSellUnitQuantity()
                            * $this->getUnitNumberInPackaging()
                    );
                } else {
                    return($this->getSellUnitQuantity()
                            * $this->getUnitNumberInConditioning()
                            * $this->getUnitNumberInPackaging()
                    );
                }
                break;
            case SELLUNITTYPE_UR:
                $sutInContainerId = $this->getSellUnitTypeInContainerId();
                if ($sutInContainerId == SELLUNITTYPE_UB) {
                    return($this->getSellUnitQuantity()
                            * $this->getUnitNumberInPackaging()
                            * $this->getUnitNumberInGrouping()
                    );
                } else {
                    return($this->getSellUnitQuantity()
                            * $this->getUnitNumberInConditioning()
                            * $this->getUnitNumberInPackaging()
                            * $this->getUnitNumberInGrouping()
                    );
                }
                break;
            default:
                return 1;
        }
    }

    // }}}
    // Product::getUVPrice() {{{

    public function getUVPrice($actor = false)
    {
        if (false == $actor) {
            $actor = $this->getMainSupplier();
        }
        if ($actor instanceof Supplier || $actor instanceof AeroSupplier) {
            $ActorProductMapper = Mapper::singleton('ActorProduct');
            $ActorProduct = $ActorProductMapper->load(
                array('Actor' => $actor->getId(), 'Product' => $this->GetId()));
            if ($ActorProduct instanceof ActorProduct) {
                // le prix d'achat du produit ds la classe ActorProduct
                return $ActorProduct->getPriceByActor();
            } else { // le prix de vente (UV) du produit ds la classe product
                return $this->getPriceByActor($actor);
            }
        }
        return 0;
    }

    // }}}
    // Product::getUBPrice() {{{

    /**
     * Product::getUBPrice()
     * Retourne le prix de l'unit� de base du produit
     *
     * @param Object $actor: si renseign� le prix est dans la devise de celui-ci
     * @return float
     **/
    public function getUBPrice($actor = false)
    {
        if ($actor instanceof Actor) {
            // si un couple actor/product existe on renvoie le prix de celui-ci
            $mapper = Mapper::singleton('ActorProduct');
            $ac = $mapper->load(array('Actor'=>$actor->getId(),
                'Product'=>$this->getId()));
            if ($ac instanceof ActorProduct) {
                return $ac->getUBPrice();
            }
        }
        $numberUBInUV = $this->GetNumberUBInUV();
        if ($numberUBInUV != 0) {
            $price = $this->GetPriceByActor($actor);
            return round($price/$numberUBInUV, 2);
        }
        return 0;
    }

    // }}}
    // Product::synchronizeVirtualQuantity() {{{

    /**
     * methode Addon : Rend la Qte Virtuelle egale a la TotalRealQuantity
     * Methode utile � l'import des donn�es, pour initialiser les VirtualQuantity
     *
     * @return int Content of value
     * @access public
     */

    public function synchronizeVirtualQuantity()
    {
        $TotalRealQuantity = $this->getRealQuantity();
        $this->setSellUnitVirtualQuantity($TotalRealQuantity);
    }

    // }}}
    // Product::packagingUnitNumber() {{{

    /**
     * Methode Addon : Retourne le nbre  d'UE en fonction d'un nbre d'UV entre
     *
     * @param int $ quantity
     * @return int Content of value
     * @param  $execution integer 0 par defaut, 1 si execution deconnectee
     * @access public
     */
    public function packagingUnitNumber($quantity, $execution = 0)
    {
        require_once('Objects/SellUnitType.const.php');
        $count = -1;
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case SELLUNITTYPE_UB:
                if (0 < $this->getUnitNumberInPackaging()) {
                    $count = $quantity * $this->getSellUnitQuantity()
                            / $this->getUnitNumberInPackaging();
                }
                elseif (0 < $this->getUnitNumberInConditioning()) {
                    $count = $quantity * $this->getSellUnitQuantity()
                            / $this->getUnitNumberInConditioning();
                }
                else {
                    $count = $quantity;
                }
                break;
            case SELLUNITTYPE_UC:
                if (0 < $this->getUnitNumberInPackaging() &&
                    0 < $this->getUnitNumberInConditioning()) {
                    $count = $quantity * $this->getSellUnitQuantity()
                            * $this->getUnitNumberInConditioning()
                            / $this->getUnitNumberInPackaging();
                }
                break;
            case SELLUNITTYPE_UR:
                if (0 < $this->getUnitNumberInGrouping()) {
                    $count = $quantity * $this->getSellUnitQuantity()
                            / $this->getUnitNumberInGrouping();
                }
                break;
            default:
                $count = $quantity * $this->getSellUnitQuantity();
        }
        // si on n'a pas pu calculer normalement car une info etait nulle
        if ($count == -1) {
            $count = $quantity;
        }
        $count = ($execution == 1)?$count:ceil($count);
        return $count;
    }

    // }}}
    // Product::getPriceTTC() {{{

    /**
     * Methode Addon: donne le prix TTC
     *
     * @return integer Content of value
     * @access public
     */
    public function getPriceTTC()
    {
        $tva = $this->getTVA();
        $tvarate = (is_object($tva) && $tva instanceof TVA)?$tva->getRate():0;
        $price = $this->getPriceByActor();
        return round($price + (($price * $tvarate) / 100), 2);
    }

    // }}}
    // Product::isDeletable() {{{

    /**
     * Methode Addon : Retourne true si le Product est supprimable
     * et false s'il n'est que desactivable
     *
     * @access public
     * @return boolean
     */
    public function isDeletable($checkNomenclature=true)
    {
        $LocationExecutedMovtMapper = Mapper::singleton('LocationExecutedMovement');
        $LEMCollection = $LocationExecutedMovtMapper->loadCollection(array('Product' => $this->GetId()));
        /*   array('State' -> array(ExecutedMovement::EN_COURS, ExecutedMovement::EXECUTE_PARTIELLEMENT)));*/
        if (!Tools::isEmptyObject($LEMCollection)) {
            return false;
        }
        $ProductCommandItemMapper = Mapper::singleton('ProductCommandItem');
        $ProductCommandItemCollection = $ProductCommandItemMapper->loadCollection(array('Product' => $this->GetId()));
        if (!Tools::isEmptyObject($ProductCommandItemCollection)) {
            return false;
        }
        if (!(0 == $this->GetRealQuantity())) {
            return false;
        }
        if ($checkNomenclature) {
            // si le produit est impliqu� dans une nomenclature mod�le,
            // il ne peut �tre supprim�
            $mapper = Mapper::singleton('Component');
            if ($mapper->alreadyExists(array('Product'=>$this->getId()))) {
                return false;
            }
        }
        return true;
    }

    // }}}
    // Product::getProductSubstitutionCollection() {{{

    /**
     * Retourne la collection des ProductSubstitutions par lesquels on peut
     * substituer le Product, independamment du SitOwner, Activated, ...
     *
     * @param $SupplierId facultatif: le Substitut a un fournisseur fixe ou non
     * @access public
     * @return void
     */
    public function getProductSubstitutionCollection($supplierId=0)
    {
        require_once('lib/SQLRequest.php');
        $ids = array();
        $rs = request_productSubstitutionds($this->getId(), $supplierId);
        while (!$rs->EOF) {
            $ids[] = intval($rs->fields['_Id']);
            $rs->moveNext();
        }

        $PdtSubstMapper = Mapper::singleton('ProductSubstitution');
        $PdtSubstCollection = $PdtSubstMapper->loadCollection(array('Id' => $ids));
        return $PdtSubstCollection;
    }

    // }}}
    // Product::getProductCollectionForSubstitution() {{{

    /**
     * Retourne une collection de produits par lesquels on peut substituer le Product
     * Les Produits retournes sont actives (sauf si param $Activated=0), et en stock
     * (facultatif: pour un SitOwner donne)
     *
     * @access public
     * @param  $SitOwnerid integer
     * @param  $lpqActivated integer : 0=> pas de restriction;
     *           1=>Restriction aux LPQ et Location actives
     * @param $SupplierId: Id du Supplier des substituts trouves
     * @param $Activated: 1 si les substituts trouves divent etre actives, 0 sinon
     * @return void
     */
    public function getProductCollectionForSubstitution($SitOwnerId=0, $lpqActivated=0, $SupplierId=0, $Activated=1)
    {
        $ProductCollection = new Collection();
        $PdtSubstitutionCollection = $this->getProductSubstitutionCollection($SupplierId);  // & php 4.4

        if (!Tools::isEmptyObject($PdtSubstitutionCollection)) {
            for($i = 0; $i < $PdtSubstitutionCollection->getCount(); $i++) {
                $item = $PdtSubstitutionCollection->getItem($i);
                $SubstitutProductId = $item->getSubstitutionForProduct($this->GetId());
                if (intval($SubstitutProductId) > 0) { // reste a voir si active, et en stock
                    $SubstitutProduct = Object::load('Product', $SubstitutProductId);
                    // reste a voir si active et si en stock
                    if ((($Activated == 1 && 1 == $SubstitutProduct->getActivated()) || $Activated == 0)
                            && $SubstitutProduct->getRealQuantity(0, $SitOwnerId, $lpqActivated) > 0) {
                        $ProductCollection->setItem($SubstitutProduct);
                    }
                }
                unset($SubstitutProduct, $item);
            } // for
        }
        return $ProductCollection;
    }

    // }}}
    // Product::productUnities() {{{

    /**
     * methode Addon : affiche le detail des unites pour le produit
     *
     * @return string
     * @access public
     */
    public function productUnities()
    {
        require_once('Objects/SellUnitType.const.php');
        $str = "";
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case 'SELLUNITTYPE_NONE':
                return 'N/A';
                break;
            case SELLUNITTYPE_UB:
                $str = _('Selling unit') . ' = ' . $this->getSellUnitQuantity()
                        . ' ' . _('Base unit');
                break;
            case SELLUNITTYPE_UC:
                $str = _('Selling unit') . ' = ' . $this->getSellUnitQuantity() . ' '
                        . _('Packing unit') . ' ** ' . _('Packing unit') . ' = '
                        . $this->getUnitNumberInConditioning() . ' ' . _('Base unit');
                break;
            case SELLUNITTYPE_UE:
                $sutInContainerId = $this->getSellUnitTypeInContainerId();
                if ($sutInContainerId == SELLUNITTYPE_UB) {
                    $str = _('Selling unit') . ' = ' . $this->getSellUnitQuantity() . ' '
                            . _('Packaging unit') . ' ** ' . _('Packaging unit') . ' = '
                            . $this->getUnitNumberInPackaging() . ' ' . _('Base unit');
                } else {
                    $str = _('Selling unit') . ' = ' . $this->getSellUnitQuantity() . ' '
                            . _('Packaging unit') . ' ** ' . _('Packaging unit')
                            . ' = ' . $this->getUnitNumberInPackaging() . ' '
                            . _('Packing unit') . ' ** ' . _('Packing unit') . ' = '
                            . $this->getUnitNumberInConditioning() . ' ' . _('Base unit');
                }
                break;
            case SELLUNITTYPE_UR:
                $sutInContainerId = $this->getSellUnitTypeInContainerId();
                if ($sutInContainerId == SELLUNITTYPE_UB) {
                    $str = _('Selling unit') . ' = ' . $this->getSellUnitQuantity() . ' '
                            . _('Regrouping unit') . ' ** ' . _('Regrouping unit')
                            . ' = ' . $this->getUnitNumberInGrouping() . ' '
                            . _('Packaging unit') . ' ** ' . _('Packaging unit') . ' = '
                            . $this->getUnitNumberInPackaging() . ' ' . _('Base unit');
                } else {
                    $str = _('Selling unit') . ' = ' . $this->getSellUnitQuantity() . ' '
                            . _('Regrouping unit') . ' ** ' . _('Regrouping unit')
                            . ' = ' . $this->getUnitNumberInGrouping() . ' '
                            . _('Packaging unit') . ' ** ' . _('Packaging unit')
                            . ' = ' . $this->getUnitNumberInPackaging() . ' '
                            . _('Packing unit') . ' ** ' . _('Packing unit') . ' = '
                            . $this->getUnitNumberInConditioning() . ' ' . _('Base unit');
                }

                break;
            default: ;
        } // switch
        return $str;
    }

    // }}}
    // Product::__call() {{{

    /**
     * Intercepteur de methodes.
     * Cela nous permet d'intercepter n'importe quel appel de methode
     *
     * @access protected
     */
    public function __call($method, $args)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        }
        $methodType = strtolower(substr($method, 0, 3));
        switch ($methodType) {
            case 'get':
                return $this->getProperty($method);
            case 'set':
                $this->setProperty($method, $args[0]);
                return true;
            default:
                return true;
        } // switch
    }

    // }}}
    // Product::getProperty() {{{

    /**
     *
     * @access public
     * @return void
     */
    public function getProperty($getter)
    {
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }
        $property = strtolower(substr($getter, 3));
        $property = substr($property, -2)=='id'?
            substr($property, 0, -2):$property;
        require_once('SQLRequest.php');
        return Request_Get_Interceptor($property, $this);
    }

    // }}}
    // Product::setProperty() {{{

    /**
     *
     * @access public
     * @return void
     */
    public function setProperty($setter, $prop_value)
    {
        if (method_exists($this, $setter)) {
            $this->$setter($prop_value);
            return;
        }
        $type = $this->getProductType();
        if ($type instanceof ProductType) {
            $property = strtolower(substr($setter, 3));
            $properties = array_change_key_case($type->getPropertyArray());
            if (array_key_exists($property, $properties)) {
                $prop = $properties[$property];
                $prop->setValue($this->getId(), $prop_value);
            }
        }
    }

    // }}}
    // Product::getCovertype() {{{

    /**
     * Retourne un CoverType pour le produit
     *
     * @return object CoverType
     * @access public
     */
    public function getCovertype()
    {
        require_once('Objects/SellUnitType.const.php');
        require_once('Objects/CoverType.const.php');
        $sutId = $this->getSellUnitTypeId();
        switch ($sutId) {
            case SELLUNITTYPE_UB:
                    $CoverType = Object::load('CoverType', CAISSE);
                    break;
                case SELLUNITTYPE_UC:
                    $CoverTypeId = Tools::getValueFromMacro(
                            $this, '%ConditioningRecommended.CoverType.Id%');
                    $CoverType = Object::load('CoverType', $CoverTypeId);
                    if (Tools::isEmptyObject($CoverType)) {
                        $CoverType = Object::load('CoverType', CAISSE);
                    }
                    break;
                case SELLUNITTYPE_UE:
                    $CoverTypeId = Tools::getValueFromMacro(
                            $this, '%PackagingRecommended.CoverType.Id%');
                    $CoverType = Object::load('CoverType', $CoverTypeId);
                    if (Tools::isEmptyObject($CoverType)) {
                        $CoverType = Object::load('CoverType', CAISSE);
                    }
                    break;
                case SELLUNITTYPE_UR:
                    $CoverTypeId = Tools::getValueFromMacro(
                            $this, '%GroupingRecommended.CoverType.Id%');
                    $CoverType = Object::load('CoverType', $CoverTypeId);
                    if (Tools::isEmptyObject($CoverType)) {
                        $CoverType = Object::load('CoverType', CAISSE);
                    }
                    break;
                default:
                    $CoverType = false;
            } // switch
            return $CoverType;
    }

    // }}}
    // Product::getMeasuringUnit() {{{

    /**
     * retourne l'unit� de mesure si le SellUnitType est au kilo, au litre, au
     * gallon ou encore � l'ounce, sinon retourne une cha�ne vide.
     *
     * @access public
     * @return string
     */
    public function getMeasuringUnit() {
        require_once('Objects/SellUnitType.const.php');
        $sut = $this->getSellUnitType();
        if ($sut instanceof SellUnitType && $sut->getId() >= SELLUNITTYPE_KG) {
            return ' ' . $sut->getShortName();
        }
        return '';
    }

    // }}}
    // Product::getSupplierCollection() {{{

    /**
     * Methode qui retourne la collection de fournisseurs pour le produit.
     * Le fournisseur prioritaire est le premier de la collection.
     *
     * @access public
     * @return mixed object Collection
     **/
    public function getSupplierCollection() {
        $apCol = $this->getActorProductCollection(array(),
            array('Priority'=>SORT_DESC));
        $count  = $apCol->getCount();
        $supCol = new Collection();
        $supCol->entityName = 'Actor';
        for ($i=0; $i<$count; $i++) {
            $ap  = $apCol->getItem($i);
            $sup = $ap->getActor();
            if ($sup instanceof Actor) {
                $supCol->setItem($sup);
            }
        }
        return $supCol;
    }

    // }}}
    // Product::getMainSupplier() {{{

    /**
     * Fonction qui retourne le fournisseur prioritaire pour le produit en
     * cours. Si aucun fournisseur n'est trouv� retourne false.
     *
     * @access public
     * @return mixed object Actor or boolean false
     **/
    public function getMainSupplier() {
        $apCol = $this->getActorProductCollection(array('Priority'=>1));
        if ($apCol instanceof Collection && $apCol->getCount() > 0) {
            // normalement il ne doit y avoir qu'un �l�ment
            $ap  = $apCol->getItem(0);
            $sup = $ap->getActor();
            if ($sup instanceof Actor) {
                return $sup;
            }
        }
        return false;
    }

    // }}}
    // Product::getReferenceByActor() {{{

    /**
     * Methode qui retourne la r�f�rence d'achat ou ref client pour le couple
     * produit/acteur pass� en param�tre;
     * Si pas d'acteur pass� on prend le MainSupplier.
     * Si aucune ref. n'est trouv�e la m�thode retourne une string vide.
     *
     * @access public
     * @param mixed object or integer $supplier instanceof Actor or Actor id
     * @return string
     **/
    public function getReferenceByActor($supplier=false) {
        if (!$supplier) {
            $supplier = $this->getMainSupplier();
        }
        if (is_int($supplier)) {
            $supplier = Object::load('Actor', $supplier);
        }
        if ($supplier instanceof Actor) {
            $apMapper = Mapper::singleton('ActorProduct');
            $ap = $apMapper->load(
                array(
                    'Actor'=>$supplier->getId(),
                    'Product'=>$this->getId()
                )
            );
            if ($ap instanceof ActorProduct) {
                return $ap->getAssociatedProductReference();
            }
        }
        return '';
    }

    // }}}
    // Product::getBuyUnitType() {{{

    /**
     * Methode qui retourne le UnitType d'achat pour le couple produit/acteur
     * pass� en param�tre, si pas d'acteur pass� on prend le MainSupplier.
     * Si aucun type. n'est trouv�e la m�thode retourne false.
     *
     * @access public
     * @param object Supplier
     * @return mixed object SellUnitType or boolean false
     **/
    public function getBuyUnitType($supplier = false) {
        if (!$supplier) {
            $supplier = $this->getMainSupplier();
        }
        if ($supplier instanceof Actor) {
            $apMapper = Mapper::singleton('ActorProduct');
            $ap = $apMapper->load(
                array(
                    'Actor'=>$supplier->getId(),
                    'Product'=>$this->getId()
                )
            );
            if ($ap instanceof ActorProduct) {
                return $ap->getBuyUnitType();
            }
        }
        return false;
    }

    // }}}
    // Product::getBuyUnitQuantity() {{{

    /**
     * Methode qui retourne la quantit� de UnitType d'achat pour le couple
     * produit/acteur pass� en param�tre, si pas d'acteur pass� on prend le
     * MainSupplier.
     *
     * @access public
     * @param object Supplier
     * @return int
     **/
    public function getBuyUnitQuantity($supplier = false) {
        if (!$supplier) {
            $supplier = $this->getMainSupplier();
        }
        if ($supplier instanceof Actor) {
            $apMapper = Mapper::singleton('ActorProduct');
            $ap = $apMapper->load(
                array(
                    'Actor'=>$supplier->getId(),
                    'Product'=>$this->getId()
                )
            );
            if ($ap instanceof ActorProduct) {
                return $ap->getBuyUnitQuantity();
            }
        }
        return 0;
    }

    // }}}
    // Product::onAfterImport() {{{

    /**
     * Fonction appel�e apr�s import de donn�es via glao-import.
     * Appel�e par le script d'import xmlrpc.
     *
     * @access public
     * @param  array $params un tableau de param�tres optionnel
     * @return boolean
     **/
    public function onAfterImport($params = array()) {
        // � l'import, si un supplier a �t� d�fini pour le produit en cours, on
        // cr�e un couple ActorProduct correspondant
        // XXX TODO
        //
        return false;

        $supplier = $this->getSupplier();
        if (!($supplier instanceof Supplier) && !($supplier instanceof AeroSupplier)) {
            // pas de supplier renseign�
            return false;
        }
        // est-ce que le couple existe d�j� ?
        $apMapper = Mapper::singleton('ActorProduct');
        if ($apMapper->alreadyExists(
            array('Product'=>$this->getId(), 'Actor'=>$supplier->getId()))) {
            // le couple existe d�j�, on ne le cr�e pas
            return false;
        }
        // on cr�e l'ActorProduct
        require_once('Objects/ActorProduct.php');
        $actor_product = new ActorProduct();
        $actor_product->setActor($supplier);
        $actor_product->setProduct($this);
        $actor_product->save();
        return true;
    }

    // }}}
    // Product::getConcreteProductInStockCollection() {{{

    /**
     * Retourne la collection des ConcreteProduct lies au Product, en stock,
     * avec des conditions sur Active et State
     *
     * @access public
     * @param integer $active
     * @param array $state
     * @param array $lazy tablea de noms d'attribut pour lazy loading
     * @return object Collection
     **/
    public function getConcreteProductInStockCollection(
            $active=1, $state=array(ConcreteProduct::EN_MARCHE, ConcreteProduct::EN_STOCK), $lazy=array()) {

        $coll = new Collection();
        $FilterComponentArray = array(); // Tableau de filtres

        $FilterComponentArray[] = SearchTools::NewFilterComponent(
            'Active', '', 'Equals', $active, 1);
        $FilterComponentArray[] = SearchTools::NewFilterComponent(
            'State', '', 'In', $state, 1);
        $FilterComponentArray[] = SearchTools::NewFilterComponent(
            'Qty', 'LocationConcreteProduct().Quantity', 'GreaterThan', 0, 1,
            'ConcreteProduct');
        $filter = SearchTools::filterAssembler($FilterComponentArray);

        $coll = $this->getConcreteProductCollection($filter, array(), $lazy);
        return $coll;
    }

    // }}}
    // Product::getPropertiesByContext() {{{

    /**
     *
     * @access public
     * @return void
     */
    public static function getPropertiesByContext()
    {
        $context = Preferences::get('TradeContext', array());
        if (in_array('readytowear', $context)) {
            return RTWProduct::getProperties();
        }
        if (in_array('aero', $context)) {
            return AeroProduct::getProperties();
        }
        return parent::getProperties();
    }

    // }}}

}

?>
