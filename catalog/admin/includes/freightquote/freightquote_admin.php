<?php
/*
  Freightquote.com Shipping Module for osCommerce 2.2 MS2

  Copyright (c) 2010 Freightquote.com
  
  Developed by Dynamo Effects - sales [at] dynamoeffects.com

  Released under the GNU General Public License v2
*/

  class freightQuote {
    var $fqDatabaseColumns, $fqDirectory;
    
    /* Constructor method */
    function freightQuote() {
      global $language;
      
      $this->fqDirectory = DIR_WS_INCLUDES . 'freightquote/';
      
      $this->fqDatabaseColumns = array(
        'products_freightquote_enable',
        'products_freightquote_class',
        'products_freightquote_length',
        'products_freightquote_width',
        'products_freightquote_height',
        'products_freightquote_nmfc',
        'products_freightquote_hzmt',
        'products_freightquote_package_type',
        'products_freightquote_commodity_type',
        'products_freightquote_content_type'
      );
      
      /* Include Language Defines */
      if (!file_exists($this->fqDirectory . 'languages/' . $language . '.php')) {
        include($this->fqDirectory . 'languages/english.php');
      } else {
        include($this->fqDirectory . 'languages/' . $language . '.php');
      }
    }
    
    /* Formats the database columns to be used in SQL queries */
    function getDatabaseColumns() {
      $fqString = implode(',', $this->fqDatabaseColumns);
      
      return $fqString;
    }
    
    /* This adds the additional columns to the empty $pInfo object */
    function mergePostParameters($params) {
      $fqParameters = array();
      
      foreach ($this->fqDatabaseColumns as $col) {
        $fqParameters[$col] = '';
      }
      
      return array_merge((array)$params, $fqParameters);
    }
    
    /* Method to merge the POST variables into the $pInfo object */
    function mergeUpdateProductParameters($params) {      
      $fqParameters = array();
      
      foreach ($this->fqDatabaseColumns as $col) {
        $fqParameters[$col] = $_POST[$col];
      }
      
      return array_merge((array)$params, $fqParameters);
    }
    
    /* Retrieves the additional database columns for that product then 
       merges the result with the previous query's result */
    function mergeDatabaseColumns($product) {
      $query = tep_db_query(
        "SELECT " . $this->getDatabaseColumns() . " " .
        "FROM " . TABLE_PRODUCTS . " " .
        "WHERE products_id = " . (int)$_GET['pID'] . " " .
        "LIMIT 1"
      );
      
      if (tep_db_num_rows($query) > 0) {
        $result = tep_db_fetch_array($query);
        
        $product = array_merge((array)$product, $result);
      }

      return $product;
    }
    
    /* Outputs the template that appears when editing a product */
    function showInterface() {
      global $pInfo;

      if (MODULE_SHIPPING_FREIGHTQUOTE_STATUS != 'True') return false;
      
      $template = array(
        'TITLE' => TEXT_PRODUCTS_FREIGHTQUOTE,
        'LABEL_ENABLE' => TEXT_PRODUCTS_FREIGHTQUOTE_ENABLE,
        'LABEL_CLASS' => TEXT_PRODUCTS_FREIGHTQUOTE_CLASS,
        'LABEL_DIMENSIONS' => TEXT_PRODUCTS_FREIGHTQUOTE_DIMENSIONS,
        'LABEL_NMFC' => TEXT_PRODUCTS_FREIGHTQUOTE_NMFC,
        'LABEL_HZMT' => TEXT_PRODUCTS_FREIGHTQUOTE_HZMT,
        'LABEL_COMMODITY_TYPE' => TEXT_PRODUCTS_FREIGHTQUOTE_COMMODITY_TYPE,
        'LABEL_PACKAGE_TYPE' => TEXT_PRODUCTS_FREIGHTQUOTE_PACKAGE_TYPE,
        'LABEL_CONTENT_TYPE' => TEXT_PRODUCTS_FREIGHTQUOTE_CONTENT_TYPE
      );
      
      $boolean_dropdown = array(
        array('id' => 'true', 'text' => 'True'),
        array('id' => 'false', 'text' => 'False')
      );
                               
      $classes = array(
        array('id' => '50', 'text' => '50'),
        array('id' => '55', 'text' => '55'),
        array('id' => '60', 'text' => '60'),
        array('id' => '65', 'text' => '65'),
        array('id' => '70', 'text' => '70'),
        array('id' => '77.5', 'text' => '77.5'),
        array('id' => '85', 'text' => '85'),
        array('id' => '92.5', 'text' => '92.5'),
        array('id' => '100', 'text' => '100'),
        array('id' => '110', 'text' => '110'),
        array('id' => '125', 'text' => '125'),
        array('id' => '150', 'text' => '150'),
        array('id' => '175', 'text' => '175'),
        array('id' => '200', 'text' => '200'),
        array('id' => '250', 'text' => '250'),
        array('id' => '300', 'text' => '300'),
        array('id' => '400', 'text' => '400'),
        array('id' => '500', 'text' => '500')
      );
      
      $packages = array(
        array('id' => 'Bags', 'text' => 'Bags'),
        array('id' => 'Bales', 'text' => 'Bales'),
        array('id' => 'Boxes', 'text' => 'Boxes'),
        array('id' => 'Bundles', 'text' => 'Bundles'),
        array('id' => 'Carpets', 'text' => 'Carpets'),
        array('id' => 'Coils', 'text' => 'Coils'),
        array('id' => 'Crates', 'text' => 'Crates'),
        array('id' => 'Cylinders', 'text' => 'Cylinders'),
        array('id' => 'Drums', 'text' => 'Drums'),
        array('id' => 'Pails', 'text' => 'Pails'),
        array('id' => 'Reels', 'text' => 'Reels'),
        array('id' => 'Rolls', 'text' => 'Rolls'),
        array('id' => 'TubesPipes', 'text' => 'Tubes/Pipes'),
        array('id' => 'Motorcycle', 'text' => 'Motorcycle'),
        array('id' => 'ATV', 'text' => 'ATV'),
        array('id' => 'Pallets_48x40', 'text' => 'Pallets 48x40'),
        array('id' => 'Pallets_other', 'text' => 'Pallets Other'),
        array('id' => 'Pallets_120x120', 'text' => 'Pallets 120x120'),
        array('id' => 'Pallets_120x100', 'text' => 'Pallets 120x100'),
        array('id' => 'Pallets_120x80', 'text' => 'Pallets 120x80'),
        array('id' => 'Pallets_europe', 'text' => 'Pallets Europe'),
        array('id' => 'Pallets_48x48', 'text' => 'Pallets 48x48'),
        array('id' => 'Pallets_60x48', 'text' => 'Pallets 60x48')
      );
      
      $commodities = array(
        array('id' => 'GeneralMerchandise', 'text' => 'General Merchandise'),
        array('id' => 'Machinery', 'text' => 'Machinery'),
        array('id' => 'HouseholdGoods', 'text' => 'Household Goods'),
        array('id' => 'FragileGoods', 'text' => 'Fragile Goods'),
        array('id' => 'ComputerHardware', 'text' => 'Computer Hardware'),
        array('id' => 'BottledProducts', 'text' => 'Bottled Products'),
        array('id' => 'BottleBeverages', 'text' => 'Bottle Beverages'),
        array('id' => 'NonPerishableFood', 'text' => 'Non Perishable Food'),
        array('id' => 'SteelSheet', 'text' => 'Steel Sheet'),
        array('id' => 'BrandedGoods', 'text' => 'Branded Goods'),
        array('id' => 'PrecisionInstruments', 'text' => 'Precision Instruments'),
        array('id' => 'ChemicalsHazardous', 'text' => 'Chemicals Hazardous'),
        array('id' => 'FineArt', 'text' => 'Fine Art'),
        array('id' => 'Automobiles', 'text' => 'Automobiles'),
        array('id' => 'CellPhones', 'text' => 'Cell Phones'),
        array('id' => 'NewMachinery', 'text' => 'New Machinery'),
        array('id' => 'UsedMachinery', 'text' => 'Used Machinery'),
        array('id' => 'HotTubs', 'text' => 'Hot Tubs')
      );
      
      $contents = array(
        array('id' => 'NewCommercialGoods', 'text' => 'New Commercial Goods'),
        array('id' => 'UsedCommercialGoods', 'text' => 'Used Commercial Goods'),
        array('id' => 'HouseholdGoods', 'text' => 'Household Goods'),
        array('id' => 'FragileGoods', 'text' => 'Fragile Goods'),
        array('id' => 'Automobile', 'text' => 'Automobile'),
        array('id' => 'Motorcycle', 'text' => 'Motorcycle'),
        array('id' => 'AutoOrMotorcycle', 'text' => 'Auto or Motorcycle')
      );
      
      $template['DROPDOWN_CLASS'] = tep_draw_pull_down_menu(
        'products_freightquote_class', 
        $classes, 
        $pInfo->products_freightquote_class
      );
      
      $template['FIELD_ENABLE'] = tep_draw_checkbox_field(
        'products_freightquote_enable',
        '1',
        ($pInfo->products_freightquote_enable == '1' ? true : false)
      );
      
      $template['FIELDS_DIMENSIONS'] = tep_draw_input_field(
        'products_freightquote_length', 
        $pInfo->products_freightquote_length, 
        'maxlength=3 style="width:50px"'
      ) . 'L x ' . 
      tep_draw_input_field(
        'products_freightquote_width', 
        $pInfo->products_freightquote_width, 
        'maxlength=3 style="width:50px"'
      ) . 'W x ' . 
      tep_draw_input_field(
        'products_freightquote_height', 
        $pInfo->products_freightquote_height, 
        'maxlength=3 style="width:50px"'
      ) . 'H';
      
      $template['FIELD_NMFC'] = tep_draw_input_field(
        'products_freightquote_nmfc', 
        $pInfo->products_freightquote_nmfc, 
        'maxlength=100'
      );
      
      $template['DROPDOWN_HZMT'] = tep_draw_pull_down_menu(
        'products_freightquote_hzmt', 
        $boolean_dropdown, 
        ($pInfo->products_freightquote_hzmt == 'true' ? 'true' : 'false')
      );
      
      $template['DROPDOWN_PACKAGE_TYPES'] = tep_draw_pull_down_menu(
        'products_freightquote_package_type', 
        $packages, 
        $pInfo->products_freightquote_package_type
      );
      
      $template['DROPDOWN_COMMODITY_TYPES'] = tep_draw_pull_down_menu(
        'products_freightquote_commodity_type', 
        $commodities, 
        $pInfo->products_freightquote_commodity_type
      );
      
      $template['DROPDOWN_CONTENT_TYPES'] = tep_draw_pull_down_menu(
        'products_freightquote_content_type', 
        $contents, 
        $pInfo->products_freightquote_content_type
      );
      
      $template_file = file_get_contents($this->fqDirectory . 'freightquote_interface.tpl');
      
      foreach ($template as $key => $val) {
        $template_file = str_replace($key, $val, $template_file);
      }
      
      echo $template_file;
    }
  }
?>