<?php
/*
  Freightquote.com Shipping Module for osCommerce 2.2 MS2

  Copyright (c) 2010 Freightquote.com
  
  Developed by Dynamo Effects - sales [at] dynamoeffects.com

  Released under the GNU General Public License v2
*/
  class freightquote {
    var $code, $title, $description, $sort_order, $icon, 
        $tax_class, $enabled, $request, $response;
    
    function freightquote() {
      global $order;

      $this->code = 'freightquote';
      $this->title = MODULE_SHIPPING_FREIGHTQUOTE_TEXT_TITLE;
      $this->description = MODULE_SHIPPING_FREIGHTQUOTE_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_SHIPPING_FREIGHTQUOTE_SORT_ORDER;
      $this->icon = '';
      $this->tax_class = MODULE_SHIPPING_FREIGHTQUOTE_TAX_CLASS;
      
      $this->enabled = (MODULE_SHIPPING_FREIGHTQUOTE_STATUS == 'True' ? true : false);
      
      if (($this->enabled == true) && ((int)MODULE_SHIPPING_FREIGHTQUOTE_ZONE > 0)) {
        $check_flag = false;
        $check_query = tep_db_query(
          "SELECT zone_id " .
          "FROM " . TABLE_ZONES_TO_GEO_ZONES . " " .
          "WHERE geo_zone_id = '" . MODULE_SHIPPING_FREIGHTQUOTE_ZONE . "' " .
          "  AND zone_country_id = '" . $order->delivery['country']['id'] . "' " .
          "ORDER BY zone_id"
        );
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }

        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
    }

    function quote($method = '') {
      global $order, $cart, $customer_id, $currencies, $shipping;
      
      //Array used to store data that will be fed into XML template
      $shipping_info = array();
      
      //This array will be turned into the XML that is sent to Freightquote.com
      $request_xml = array(
        'GetRatingEngineQuote' => array(
          'request' => array(
            'CustomerId' => $customer_id,
            'QuoteType' => 'B2B',
            'ServiceType' => MODULE_SHIPPING_FREIGHTQUOTE_SERVICE_TYPE,
            'QuoteShipment' => array(
              'ShipmentLabel' => MODULE_SHIPPING_FREIGHTQUOTE_TEXT_SHIPMENT_LABEL,
              'IsBlind' => (MODULE_SHIPPING_FREIGHTQUOTE_BLIND == 'True' ? 'true' : 'false'),
              'PickupDate' => $this->next_business_day(),
              'ShipmentLocations' => array(
                'Location' => array()
              ),
              'ShipmentProducts' => array(
                'Product' => array()
              )
            )
          ),
          'user' => array(
            'Name' => MODULE_SHIPPING_FREIGHTQUOTE_USERNAME,
            'Password' => MODULE_SHIPPING_FREIGHTQUOTE_PASSWORD
          )
        )
      );
      
      //ZIP Code and country code of the destination address
      $dest_zip = $order->delivery['postcode'];
      $dest_country = $order->delivery['country']['iso_code_2'];
      
      //Format the ZIP codes for the US and Canada
      if ($dest_country == 'US') {
        $dest_zip = preg_replace('/[^0-9]/', '', $dest_zip);
        $dest_zip = substr($dest_zip, 0, 5);
      } elseif ($dest_country == 'CA') {
        $dest_zip = preg_replace('/[^0-9A-Z]/', '', strtoupper($dest_zip));
        $dest_zip = substr($dest_zip, 0, 6);
      }
      
      $ship_country = tep_get_countries(MODULE_SHIPPING_FREIGHTQUOTE_SHIP_COUNTRY, true);
      
      $ship_country = $ship_country['countries_iso_code_2'];
      
      //Retrieve all products in the cart
      $products = $cart->get_products();

      //Determine the type of location selected by the customer
      $delivery_location = 1;
      
      if (isset($_REQUEST['freightquote_delivery_location'])) {
        $delivery_location = (int)$_REQUEST['freightquote_delivery_location'];
      }
      
      $delivery_info = array();
      
      switch ($delivery_location) {
        case '0':
          $delivery_info['residence'] = 'true';
          $delivery_info['construction_site'] = 'false';
          $delivery_info['loading_dock'] = 'false';
          break;
        case '2':
          $delivery_info['residence'] = 'false';
          $delivery_info['construction_site'] = 'false';
          $delivery_info['loading_dock'] = 'true';
          break;
        case '3':
          $delivery_info['residence'] = 'false';
          $delivery_info['construction_site'] = 'true';
          $delivery_info['loading_dock'] = 'false';
          break;
        case '1':
        default:
          $delivery_info['residence'] = 'false';
          $delivery_info['construction_site'] = 'false';
          $delivery_info['loading_dock'] = 'false';
          break;
      }
      
      $request_xml['GetRatingEngineQuote']['request']['QuoteShipment']['ShipmentLocations']['Location'] = array(
        array(
          'LocationName' => STORE_NAME,
          'LocationType' => 'Origin',
          'HasLoadingDock' => strtolower(MODULE_SHIPPING_FREIGHTQUOTE_LOADING_DOCK),
          'IsConstructionSite' => strtolower(MODULE_SHIPPING_FREIGHTQUOTE_CONSTRUCTION_SITE),
          'IsResidential' => strtolower(MODULE_SHIPPING_FREIGHTQUOTE_RESIDENCE),
          'LocationAddress' => array(
            'PostalCode' => MODULE_SHIPPING_FREIGHTQUOTE_SHIP_ZIP,
            'CountryCode' => $ship_country
          )
        ),
        array(
          'LocationName' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
          'LocationType' => 'Destination',
          'HasLoadingDock' => $delivery_info['loading_dock'],
          'IsConstructionSite' => $delivery_info['construction_site'],
          'IsResidential' => $delivery_info['residence'],
          'LocationAddress' => array(
            'PostalCode' => $dest_zip,
            'CountryCode' => $dest_country
          )
        )
      );
      
      $product_list = array();
      
      $counter = 1;
      
      $excluded_items = 0;
      
      foreach ($products as $product) {
        $product_query = tep_db_query(
          "SELECT products_freightquote_enable, products_freightquote_length, products_freightquote_width, " .
          "       products_freightquote_height, products_freightquote_class, products_freightquote_nmfc, " .
          "       products_freightquote_hzmt, products_freightquote_content_type, " .
          "       products_freightquote_package_type, products_freightquote_commodity_type " .
          "FROM " . TABLE_PRODUCTS . " " .
          "WHERE products_id = " . (int)$product['id'] . " " .
          "LIMIT 1"
        );
        
        $data = tep_db_fetch_array($product_query);
        
        if ($data['products_freightquote_enable'] == '1') {
          $product_list[] = array(
            'Class' => (tep_not_null($data['products_freightquote_class']) ? $data['products_freightquote_class'] : '50'),
            'ProductDescription' => $product['name'],
            'Weight' => ceil($product['quantity'] * (int)$product['weight']),
            'Length' => ceil($data['products_freightquote_length']),
            'Width' => ceil($data['products_freightquote_width']),
            'Height' => ceil($data['products_freightquote_height']),
            'PackageType' => (tep_not_null($data['products_freightquote_package_type']) ? $data['products_freightquote_package_type'] : 'Boxes'),
            'DeclaredValue' => round($product['price']),
            'CommodityType' => (tep_not_null($data['products_freightquote_commodity_type']) ? $data['products_freightquote_commodity_type'] : 'GeneralMerchandise'),
            'ContentType' => (tep_not_null($data['products_freightquote_content_type']) ? $data['products_freightquote_content_type'] : 'NewCommercialGoods'),
            'IsHazardousMaterial' => $data['products_freightquote_hzmt'],
            'NMFC' => $data['products_freightquote_nmfc'],
            'PieceCount' => $product['quantity'],
            'ItemNumber' => $counter
          );
          
          $counter++;
        } else {
          $excluded_items++;
        }
      }
      
      $total_products = count($product_list);
      
      if (count($total_products) < 1) {
        if (MODULE_SHIPPING_FREIGHTQUOTE_DEBUG == 'True') {
          return array(
            'module' => $this->title,
            'error' => "DEBUG: The products in your cart are not configured to be used with Freightquote.com"
          );
        }
        
        return false;
      }
      
      /* Maximum 6 products allowed per query, so repeat the query multiple times if necessary */
      $freightquote_queries = array();
      $query_string = '';
      
      /*
       * Only 6 items allowed per query
       */
      for ($x = 0; $x < $total_products; $x+=6) {
        $product_request = array();
        
        for ($n = 1; $n <= 6; $n++) {
          $ret = ($n + $x) - 1;
          if (isset($product_list[$ret])) {
            $product_request[] = $product_list[$ret];
          }
        }
        
        $request_xml['GetRatingEngineQuote']['request']['QuoteShipment']['ShipmentProducts']['Product'] = $product_request;
        
        $request_result = $this->query_rates($request_xml);
        
        if (isset($request_result['error'])) {
          return array(
            'module' => $this->title,
            'error' => $request_result['error']
          );
        }

        $freightquote_queries[] = $request_result['GetRatingEngineQuoteResponse'][0]['GetRatingEngineQuoteResult'][0];
      }

      $total_shipping_price = array(
        'rate' => 0, 
        'shipment_id' => ''
      );
      
      $errors = array();

      foreach ($freightquote_queries as $quote) {
        if (is_array($quote['QuoteCarrierOptions'])) {
          if ($total_shipping_price['shipment_id'] != '') $total_shipping_price['shipment_id'] .= ' & ';
          $total_shipping_price['shipment_id'] .= $quote['QuoteId'];
          
          $total_shipping_price['rate'] += preg_replace('/[^0-9\.]/', '', $quote['QuoteCarrierOptions'][0]['CarrierOption'][0]['QuoteAmount']);
        } elseif (count($quote['ValidationErrors']) > 0) {
          foreach ($quote['ValidationErrors'][0]['B2BError'] as $error_msg) {
            $errors[] = $error_msg['ErrorMessage'];
          }
        }
      }
      
      /* If the shipping price is 0 and no errors were returned, don't display this shipping option */
      if ($total_shipping_price['rate'] <= 0 && count($errors) < 1) {
        if (MODULE_SHIPPING_FREIGHTQUOTE_DEBUG == 'True') {
          return array(
            'module' => $this->title,
            'error' => "DEBUG: No shipping rates or errors were returned from Freightquote.com, so no shipping option is being returned."
          );
        }
        
        return false;
      }
      
      //Add price modifier
      if (MODULE_SHIPPING_FREIGHTQUOTE_PRICE_MODIFIER > 0) {
        $total_shipping_price['rate'] = $total_shipping_price['rate'] * MODULE_SHIPPING_FREIGHTQUOTE_PRICE_MODIFIER;
      }
      
      //Add handling charges
      $total_shipping_price['rate'] += MODULE_SHIPPING_FREIGHTQUOTE_HANDLING * $n;

      $shipping_form = '';
      if ($_POST['action'] != 'process') {
        $shipping_form  = '<table border="0" cellspacing="0" cellpadding="2">';
        $shipping_form .= '<tr><td class="main" width="120">Delivery Location:</td><td class="main">';
        $shipping_form .= '<select name="freightquote_delivery_location" onchange="window.location.href=\'' . tep_href_link(FILENAME_CHECKOUT_SHIPPING, 'freightquote_delivery_location=') . '\'+this.value">';
        $shipping_form .= '<option value="0"' . ($delivery_location == '0' ? ' SELECTED' : '') . '>Residence</option>';
        $shipping_form .= '<option value="1"' . (!isset($delivery_location) || $delivery_location == '1' ? ' SELECTED' : '') . '>Commercial (no loading dock)</option>';
        $shipping_form .= '<option value="2"' . ($delivery_location == '2' ? ' SELECTED' : '') . '>Commercial (with loading dock)</option>';
        $shipping_form .= '<option value="3"' . ($delivery_location == '3' ? ' SELECTED' : '') . '>Construction Site</option>';
        $shipping_form .= '</select></td></tr>';
        $shipping_form .= '</table>';
      } else {
        $shipping_form  = '<br>Delivery Location: ';
        switch ($delivery_location) {
          case '0':
            $shipping_form .= 'Residence';
            break;
          case '2':
            $shipping_form .= 'Commercial (with loading dock)';
            break;
          case '3':
            $shipping_form .= 'Construction Site';
            break;
          case '1':
          default:
            $shipping_form .= 'Commercial (no loading dock)';
            break;
        }
      }

      if (count($errors) < 1) {
        $shipping_options = array();
        
        $shipping_options[] = array('id' => 'CHEAPEST',
                                    'title' => MODULE_SHIPPING_FREIGHTQUOTE_TEXT_WAY . $total_shipping_price['shipment_id'] . ($excluded_items > 0 ? MODULE_SHIPPING_FREIGHTQUOTE_TEXT_EXCLUDED . $excluded_items : '') . $shipping_form,
                                    'cost' => $total_shipping_price['rate'],
                                    'tfrc_quote_id' => $total_shipping_price['shipment_id']);

        $this->quotes = array('id' => $this->code,
                              'module' => MODULE_SHIPPING_FREIGHTQUOTE_TEXT_TITLE,
                              'methods' => $shipping_options);            
  
        if (isset($_REQUEST['freightquote_delivery_location'])) {
          $shipping = array(
            'id' => $this->code . '_CHEAPEST',
            'title' => $this->quotes['methods'][0]['title'],
            'cost' => $this->quotes['methods'][0]['title'],
            'module' => $this->code
          );
        }
        if ($this->tax_class > 0) {
          $this->quotes['tax'] = tep_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
  
        if (tep_not_null($this->icon)) $this->quotes['icon'] = tep_image($this->icon, $this->title);
      } else {
        $error_message = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
        
        $this->quotes = array('module' => $this->title . $shipping_form,
                              'error' => $error_message);
      }
      return $this->quotes;
    }
        
    function query_rates($request_xml) {
      //Make sure cURL exists
      if (!function_exists('curl_init')) {
        return array('error' => MODULE_SHIPPING_FREIGHTQUOTE_TEXT_ERROR_CURL);
      }
      
      //URL to send requests to
      $service_url = 'https://b2b.Freightquote.com/WebService/QuoteService.asmx';
      
      //XML template file used for request
      $query_xml = $xml = $this->array_to_xml($request_xml);
    
      //Make sure the XML file exists

      $this->request = $query_xml;
      
      //Initialize curl
      $ch = curl_init();
      
      $headers = array(
        'Content-Type: text/xml; charset=utf-8',
        'Content-Length: ' . strlen($this->request),
        'SOAPAction: "http://tempuri.org/GetRatingEngineQuote"'
      );

      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
      curl_setopt($ch, CURLOPT_HEADER, 0); 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_TIMEOUT, 180);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_URL, $service_url);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request); 
      
      $this->response = curl_exec($ch);

      if (curl_errno($ch) == 0) {
        curl_close($ch);
        //Simple check to make sure that this is a valid XML response

        if (strpos(strtolower($this->response), 'soap:envelope') === false) {
          return array('error' => MODULE_SHIPPING_FREIGHTQUOTE_TEXT_ERROR_DESCRIPTION);
        }

        if ($this->response) {
          //Convert the XML into an easy-to-use associative array
          $this->response = $this->parse_xml($this->response);       
        }
        
        return $this->response;
      } else {
        //Collect the error returned
        $curl_errors = curl_error($ch) . ' (Error No. ' . curl_errno($ch) . ')';

        curl_close($ch);
        
        return array('error' => $curl_errors);
      }
    }

    function array_to_xml($array, $wrapper = true) {
      $xml = '';
      
      if ($wrapper) {
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
                 '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n" .
                 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' . "\n" .
               '<soap:Body>' . "\n";
      }
      
      $first_key = true;
      
      foreach ($array as $key => $value) {
        $position = 0;
        
        if (is_array($value)) {
          $is_value_assoc = $this->is_assoc($value);
          $xml .= "<$key" . ($first_key ? ' xmlns="http://tempuri.org/"' : '') . ">\n";
          $first_key = false;
          
          foreach ($value as $key2 => $value2) {
            if (is_array($value2)) {
              if ($is_value_assoc) {
                $xml .= "<$key2>\n" . $this->array_to_xml($value2, false) . "</$key2>\n";
              } elseif (is_array($value2)) {
                $xml .= $this->array_to_xml($value2, false);
                $position++;
                
                if ($position < count($value) && count($value) > 1) $xml .= "</$key>\n<$key>\n";
              }
            } else {
              $xml .= "<$key2>" . $this->xml_safe($value2) . "</$key2>\n";
            }
          }
          $xml .= "</$key>\n";
        } else {
        
          $xml .= "<$key>" . $this->xml_safe($value) . "</$key>\n";
        }
      }
      
      if ($wrapper) {
        $xml .= '</soap:Body>' . "\n" .
              '</soap:Envelope>';
      }
      
      return $xml;
    }
    
    function is_assoc($array) {
      return (is_array($array) && 0 !== count(array_diff_key($array, array_keys(array_keys($array)))));
    }

    function parse_xml($text) {
      $reg_exp = '/<(\w+)[^>]*>(.*?)<\/\\1>/s';
      preg_match_all($reg_exp, $text, $match);
      foreach ($match[1] as $key=>$val) {
        if ( preg_match($reg_exp, $match[2][$key]) ) {
            $array[$val][] = $this->parse_xml($match[2][$key]);
        } else {
            $array[$val] = $match[2][$key];
        }
      }
      return $array;
    }
    
    function xml_safe($str) {
      //The 5 evil characters in XML
      $str = str_replace('<', '&lt;', $str);
      $str = str_replace('>', '&gt;', $str);
      $str = str_replace('&', '&amp;', $str);
      $str = str_replace("'", '&apos;', $str);
      $str = str_replace('"', '&quot;', $str);

      return $str;
    }
  
    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query(
          "SELECT configuration_value " .
          "FROM " . TABLE_CONFIGURATION . " " .
          "WHERE configuration_key = 'MODULE_SHIPPING_FREIGHTQUOTE_STATUS' " . 
          "LIMIT 1"
        );
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }
    
    function next_business_day() {
      $next_date = date("U")+86400;

      $workday = date("w", $next_date);
      
      if ($workday > 0 && $workday < 6) {
        return date(DATE_ATOM, $next_date);
      } else {
        while ($workday < 1 || $workday > 5) {
          $next_date += 86400;
          $workday = date("w", $next_date);
          if ($workday > 0 && $workday < 6) {
            return date(DATE_ATOM, $next_date);
          }
        }
      }
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Freightquote.com Shipping Module', 'MODULE_SHIPPING_FREIGHTQUOTE_STATUS', 'True', 'Do you want to offer Freightquote.com shipping?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Freightquote.com Debug Mode', 'MODULE_SHIPPING_FREIGHTQUOTE_DEBUG', 'False', 'Enable debug mode?  This will dump the data string to the user\'s screen when an error occurs.  Should not be turned on unless there are problems.', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Username', 'MODULE_SHIPPING_FREIGHTQUOTE_USERNAME', 'xmltest@Freightquote.com', 'Enter your Freightquote.com Username', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Password', 'MODULE_SHIPPING_FREIGHTQUOTE_PASSWORD', 'XML', 'Enter your Freightquote.com Password', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Service Type', 'MODULE_SHIPPING_FREIGHTQUOTE_SERVICE_TYPE', 'LTL', 'What service type do you want to use?', '6', '0', 'tep_cfg_select_option(array(\'LTL\', \'Truckload\', \'Europe\', \'Groupage\', \'Haulage\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Shipping Origin: Zip Code', 'MODULE_SHIPPING_FREIGHTQUOTE_SHIP_ZIP', '', 'What zip code will you be shipping from?', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Origin: Country', 'MODULE_SHIPPING_FREIGHTQUOTE_SHIP_COUNTRY', '223', 'What country will you be shipping from?', '6', '1', 'tep_get_country_name', 'tep_cfg_pull_down_country_list(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping Origin: Loading Dock', 'MODULE_SHIPPING_FREIGHTQUOTE_LOADING_DOCK', 'True', 'Does the shipping location have a loading dock?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping Origin: Residence', 'MODULE_SHIPPING_FREIGHTQUOTE_RESIDENCE', 'True', 'Is the shipping location a residence?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping Origin: Construction Site', 'MODULE_SHIPPING_FREIGHTQUOTE_CONSTRUCTION_SITE', 'True', 'Is the shipping location a construction site?', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Blind Ship', 'MODULE_SHIPPING_FREIGHTQUOTE_BLIND', 'True', 'Blind shipments are used to keep the originating location and receiving destination unaware of each other.', '6', '0', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Price Modifier', 'MODULE_SHIPPING_FREIGHTQUOTE_PRICE_MODIFIER', '1', 'This number will be multiplied by the total shipping rate.  If you\'d like to increase or decrease the price returned, modify this field.  e.g. For a 10% price increase, enter 1.1', '6', '1', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee', 'MODULE_SHIPPING_FREIGHTQUOTE_HANDLING', '0', 'Handling fee for this shipping method (per item).', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_FREIGHTQUOTE_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '0', 'tep_get_tax_class_title', 'tep_cfg_pull_down_tax_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Shipping Zone', 'MODULE_SHIPPING_FREIGHTQUOTE_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '0', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_SHIPPING_FREIGHTQUOTE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      
      $col_query = tep_db_query("SHOW COLUMNS FROM " . TABLE_PRODUCTS);
      
      $found = array(
        'products_freightquote_enable' => array('type' => "CHAR( 1 ) DEFAULT '0' NOT NULL"),
        'products_freightquote_class' => array('type' => "VARCHAR( 6 ) DEFAULT '50' NOT NULL"),
        'products_freightquote_length' => array('type' => "INT DEFAULT '0' NOT NULL"),
        'products_freightquote_width' => array('type' => "INT DEFAULT '0' NOT NULL"),
        'products_freightquote_height' => array('type' => "INT DEFAULT '0' NOT NULL"),
        'products_freightquote_nmfc' => array('type' => "VARCHAR(32) NULL"),
        'products_freightquote_hzmt' => array('type' => "VARCHAR(5) DEFAULT 'false' NOT NULL"),
        'products_freightquote_package_type' => array('type' => "VARCHAR(32) DEFAULT 'Boxes' NOT NULL"),
        'products_freightquote_commodity_type' => array('type' => "VARCHAR(32) DEFAULT 'GeneralMerchandise' NOT NULL"),
        'products_freightquote_content_type' => array('type' => "VARCHAR(32) DEFAULT 'NewCommercialGoods' NOT NULL")
      );
      
      while ($col = tep_db_fetch_array($col_query)) {
        $columns[] = $col['Field'];
      }

      foreach ($found as $col => $info) {
        if (!in_array($col, $columns)) {
          tep_db_query("ALTER TABLE " . TABLE_PRODUCTS . " ADD `" . $col . "` " . $info['type']);
        }
      }
    }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array(
        'MODULE_SHIPPING_FREIGHTQUOTE_STATUS',
        'MODULE_SHIPPING_FREIGHTQUOTE_DEBUG',
        'MODULE_SHIPPING_FREIGHTQUOTE_USERNAME',
        'MODULE_SHIPPING_FREIGHTQUOTE_PASSWORD',
        'MODULE_SHIPPING_FREIGHTQUOTE_SERVICE_TYPE',
        'MODULE_SHIPPING_FREIGHTQUOTE_SHIP_ZIP',
        'MODULE_SHIPPING_FREIGHTQUOTE_SHIP_COUNTRY',
        'MODULE_SHIPPING_FREIGHTQUOTE_LOADING_DOCK',
        'MODULE_SHIPPING_FREIGHTQUOTE_RESIDENCE',
        'MODULE_SHIPPING_FREIGHTQUOTE_CONSTRUCTION_SITE',
        'MODULE_SHIPPING_FREIGHTQUOTE_BLIND',
        'MODULE_SHIPPING_FREIGHTQUOTE_PRICE_MODIFIER',
        'MODULE_SHIPPING_FREIGHTQUOTE_HANDLING',
        'MODULE_SHIPPING_FREIGHTQUOTE_TAX_CLASS',
        'MODULE_SHIPPING_FREIGHTQUOTE_ZONE',
        'MODULE_SHIPPING_FREIGHTQUOTE_SORT_ORDER'
      );
    }
  }
?>