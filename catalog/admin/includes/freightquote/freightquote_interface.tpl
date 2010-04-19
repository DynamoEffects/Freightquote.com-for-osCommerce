          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td colspan="2">
              <style>
                #freightquote-container {
                  background-color: #efefef;
                  border: 1px solid #dedede;
                  font-family: Arial, Verdana, sans-serif;
                  padding: 10px;
                }

                #freightquote-container h1 {
                  margin: 0 0 10px 0;
                  font-size: 18px;
                }

                #freightquote-container label {
                  padding: 10px;
                  display: inline-block;
                  width: 160px;
                  font-size: 12px;
                }
              </style>
              <div id="freightquote-container">
                <h1>TITLE</h1>
                
                <label for="products_freightquote_enable">LABEL_ENABLE</label>
                FIELD_ENABLE
                <br />
                <label for="products_freightquote_class">LABEL_CLASS</label>
                DROPDOWN_CLASS
                <br />
                <label for="">LABEL_DIMENSIONS</label>
                FIELDS_DIMENSIONS
                <br />
                <label for="">LABEL_NMFC</label>
                FIELD_NMFC
                <br />
                <label for="">LABEL_HZMT</label>
                DROPDOWN_HZMT
                <br />
                <label for="">LABEL_PACKAGE_TYPE</label>
                DROPDOWN_PACKAGE_TYPES
                <br />
                <label for="">LABEL_COMMODITY_TYPE</label>
                DROPDOWN_COMMODITY_TYPES
                <br />
                <label for="">LABEL_CONTENT_TYPE</label>
                DROPDOWN_CONTENT_TYPES
              </div>
            </td>
          </tr>
