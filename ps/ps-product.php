<?php
include('../connection.php');
include('../config.php');

$cron_script_returned = '';

// Define Prestashop Authentication

$ps_api_key = '';
$ps_api_endpoint = '/api/products';
$ps_api_endpoint_sku = '/api/combinations';


// Loop through each product within the database and send the product to prestashop if it hasn't already been sent

$selectQuery = mysqli_query($mysqli, "SELECT productid, styleno, description, brand, model, cost, rrp, dropship, supplier, prd_description, prd_def_colour, PRD_CAT_ID FROM productheader AS ph LEFT JOIN mapping AS m ON productid = m.value_2 AND m.integration = 'PRESTASHOP' AND m.value_1 = 'PRODUCT' LEFT JOIN productcategory AS pc ON pc.PRD_STYLE_NO = ph.styleno WHERE m.value_2 IS NULL GROUP BY ph.styleno ORDER BY ph.productid ASC");
if (mysqli_num_rows($selectQuery) > 0) {

    header('Content-Type: text/text');
    while ($product = mysqli_fetch_assoc($selectQuery)) {

        // Get PS Manufacturer of current product

        // Translate product brand to brand ID
        $cdb_brand_desc = $product['brand'];
        $brandQuery = mysqli_query($mysqli, "SELECT brandid FROM brands WHERE branddesc = '$cdb_brand_desc'");
        if (mysqli_num_rows($brandQuery) > 0) {
        $brandData = mysqli_fetch_assoc($brandQuery);
        $cdb_brand_id = $brandData['brandid'];
        } else {
            $cdb_brand_id = 'NOBRAND';
        }

        if ($cdb_brand_id != 'NOBRAND') {
        $manufacturerQuery = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'BRAND' AND value_2 = '$cdb_brand_id'");
        $manufacturerData = mysqli_fetch_assoc($manufacturerQuery);

        $ps_brand_id = $manufacturerData['value_3'];
        } else {
            $ps_brand_id = 'NOBRAND';
        }

        // Get PS supplier of current product

        // Translate product supplier code to CDB supplier ID
        $cdb_supplier_code = $product['supplier'];
        $supplierQuery = mysqli_query($mysqli, "SELECT supplierid FROM supplier WHERE suppliercode = '$cdb_supplier_code'");
        if (mysqli_num_rows($supplierQuery) > 0) {
        $supplierData = mysqli_fetch_assoc($supplierQuery);
        $cdb_supplier_id = $supplierData['supplierid'];
        } else {
            $cdb_supplier_id = 'NOSUPPLIER';
        }

        if ($cdb_supplier_id != 'NOSUPPLIER') {
        $psSupplierQuery = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'SUPPLIER' AND value_2 = '$cdb_supplier_id'");
        $psSupplierData = mysqli_fetch_assoc($psSupplierQuery);

        $ps_supplier_id = $psSupplierData['value_3'];
        } else {
            $ps_supplier_id = 'NOSUPPLIER';
        }

        // Get PS category ID for default product category
        $cdb_def_category_id = $product['PRD_CAT_ID'];

        $psDefCatQuery = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'CATEGORY' AND value_2 = '$cdb_def_category_id'");
        if (mysqli_num_rows($psDefCatQuery) > 0) {
        $psDefCatData = mysqli_fetch_assoc($psDefCatQuery);
        $ps_def_category_id = $psDefCatData['value_3'];
        } else {
            $ps_def_category_id = 'NOCATEGORY';
        }
        // Is product online only?

        if ($product['dropship'] == 'Y') {
            $ps_online_only_flag = 1;
        } else {
            $ps_online_only_flag = 0;
        }

        // Build XML to send to PS

        $ps_cat_xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $ps_cat_xml .= '<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">';

        // Product Header Content


        $ps_cat_xml .= '<product>';
        if ($ps_brand_id != 'NOBRAND') {
        $ps_cat_xml .= '<id_manufacturer>' . $ps_brand_id . '</id_manufacturer>';
        }
        if ($ps_supplier_id != 'NOSUPPLIER') {
        $ps_cat_xml .= '<id_supplier>' . $ps_supplier_id . '</id_supplier>';
        }
        if ($ps_def_category_id != 'NOCATEGORY') {
        $ps_cat_xml .= '<id_category_default>' . $ps_def_category_id . '</id_category_default>';
        }
        $ps_cat_xml .= '<type>0</type>';
        $ps_cat_xml .= '<reference>' . $product['styleno'] . '</reference>';
        $ps_cat_xml .= '<is_virtual>0</is_virtual>';
        $ps_cat_xml .= '<product_type>combinations</product_type>';
        $ps_cat_xml .= '<on_sale>0</on_sale>';
        $ps_cat_xml .= '<online_only>' . $ps_online_only_flag . '</online_only>';
        $ps_cat_xml .= '<price>' . $product['rrp'] . '</price>';
        $ps_cat_xml .= '<wholesale_price>' . $product['cost'] . '</wholesale_price>';
        $ps_cat_xml .= '<unit_price>' . $product['rrp'] . '</unit_price>';
        $ps_cat_xml .= '<customizable>0</customizable>';
        $ps_cat_xml .= '<active>1</active>';
        $ps_cat_xml .= '<available_for_order>1</available_for_order>';
        $ps_cat_xml .= '<condition>new</condition>';
        $ps_cat_xml .= '<visibility>both</visibility>';
        $ps_cat_xml .= '<show_price>1</show_price>';
        $ps_cat_xml .= '<state>1</state>';
        $ps_cat_xml .= '<name><language id="1">' . $product['description'] . '</language></name>';
        $ps_cat_xml .= '<description><language id="1">' . $product['prd_description'] . '</language></description>';
        $ps_cat_xml .= '<associations>';
        $ps_cat_xml .= '<categories nodeType="category" api="categories">';
        // While Loop through all product categories
        // Define current product style
        $cdb_current_style = $product['styleno'];
        $productCategoryQuery = mysqli_query($mysqli, "SELECT PRD_CAT_ID, value_3 FROM productcategory LEFT JOIN mapping ON productcategory.prd_cat_id = mapping.value_2 WHERE prd_style_no = '$cdb_current_style' AND integration = 'PRESTASHOP' AND value_1 = 'CATEGORY'");
            if (mysqli_num_rows($productCategoryQuery) > 0) {

                while ($productCategory = mysqli_fetch_assoc($productCategoryQuery)) {
                    // With current category, generate XML for association
                    $ps_cat_xml .= '<category><id>' . $productCategory['value_3'] . '</id></category>';
                }
            } else {
                // No categories to associate with product
            }
        $ps_cat_xml .= '</categories>';
        $ps_cat_xml .= '</associations>';
        $ps_cat_xml .= '</product>';

        $ps_cat_xml .= '</prestashop>';

        echo $ps_cat_xml;


        $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $ps_api_endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $ps_cat_xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/xml',
        'Authorization: Basic ' . base64_encode($ps_api_key . ':')
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    echo 'Returned XML: ';
    echo $result;


    curl_close($ch);

    // Retrieve <id> from result and insert into integration mapping table

    $result_xml = new SimpleXMLElement($result);

    // Extract created ID value

    $ps_created_id = (string)$result_xml->product->id;


    $ps_cdb_id = $product['productid'];

    $insertQuery = mysqli_query($mysqli, "INSERT into mapping (integration, value_1, value_2, value_3) VALUES ('PRESTASHOP', 'PRODUCT', '$ps_cdb_id', '$ps_created_id')");

    }

} else {
    $cron_script_returned = 'No Products to Create';
}

$ps_cat_xml = '';

// Loop through each product SKU within the database and send the product SKU to prestashop if it hasn't already been sent

$selectQuery = mysqli_query($mysqli, "SELECT skuid, styleno, sku, ean1, mpn1, cost, rrp, colourid, fitid, sizingid FROM productline AS pl LEFT JOIN mapping AS m ON skuid = m.value_2 AND m.integration = 'PRESTASHOP' AND m.value_1 = 'SKU' LEFT JOIN colours ON pl.colour = colours.colourcode LEFT JOIN fit ON pl.fit = fit.fitcode LEFT JOIN sizing ON pl.size = sizing.sizingcode WHERE m.value_2 IS NULL GROUP BY pl.sku ORDER BY pl.skuid ASC LIMIT 100;");
if (mysqli_num_rows($selectQuery) > 0) {

    while ($productSku = mysqli_fetch_assoc($selectQuery)) {

        // Build variables for use within XML request

        // Get Master Product ID by style

        $cdb_master_prd_id = $productSku['styleno'];

        $psMasterQuery = mysqli_query($mysqli, "SELECT ph.styleno, ph.rrp, value_3 FROM productheader AS ph LEFT JOIN mapping AS m ON m.value_2 = ph.productid WHERE integration = 'PRESTASHOP' AND value_1 = 'PRODUCT' AND ph.styleno = '$cdb_master_prd_id';");
        $psMasterData = mysqli_fetch_assoc($psMasterQuery);

        $ps_master_prd_id = $psMasterData['value_3'];
        $ps_master_rrp = $psMasterData['rrp'];

        $ps_sku_ean = $productSku['ean1'];

        // Check EAN has 13 digits, if not, do not include within XML

        if (strlen($ps_sku_ean) < 13 | strlen($ps_sku_ean) > 13) {
            $ps_sku_ean = 'NOINCLUDE';
        }

        // Check EAN is only digits, if not, do not include within XML

        if (is_numeric($ps_sku_ean) == False) {
            $ps_sku_ean = 'NOINCLUDE';
        }

        $ps_sku_mpn = $productSku['mpn1'];

        if (strlen($ps_sku_mpn) < 1) {
            $ps_sku_mpn = 'NOINCLUDE';
        }
        $ps_sku_reference = $productSku['sku'];
        $ps_wholesale = $productSku['cost'];

        // Calculate combination price

        $ps_combination_price = $productSku['rrp'] - $ps_master_rrp;

        // Get PS attribute 1, 2, 3 IDs

        $cdb_a1_id = $productSku['colourid'];
        $cdb_a2_id = $productSku['fitid'];
        $cdb_a3_id = $productSku['sizingid'];

        $psA1Query = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'A1' AND value_2 = '$cdb_a1_id'");
        $psA2Query = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'A2' AND value_2 = '$cdb_a2_id'");
        $psA3Query = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'A3' AND value_2 = '$cdb_a3_id'");

        $psA1Data = mysqli_fetch_assoc($psA1Query);
        $psA2Data = mysqli_fetch_assoc($psA2Query);
        $psA3Data = mysqli_fetch_assoc($psA3Query);

        $ps_a1_id = $psA1Data['value_3'];
        $ps_a2_id = $psA2Data['value_3'];
        $ps_a3_id = $psA3Data['value_3'];
        

        // Build XML to send to PS

        $ps_cat_xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $ps_cat_xml .= '<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">';

        // Product Header Content


        $ps_cat_xml .= '<combination>';
        $ps_cat_xml .= '<id_product>' . $ps_master_prd_id . '</id_product>';
        if ($ps_sku_ean != 'NOINCLUDE') {
        $ps_cat_xml .= '<ean13>' . $ps_sku_ean . '</ean13>';
        }
        if ($ps_sku_mpn != 'NOINCLUDE') {
        $ps_cat_xml .= '<mpn>' . $ps_sku_mpn . '</mpn>';
        }
        $ps_cat_xml .= '<reference>' . $ps_sku_reference . '</reference>';
        $ps_cat_xml .= '<wholesale_price>' . $ps_wholesale . '</wholesale_price>';
        $ps_cat_xml .= '<price><![CDATA[' . $ps_combination_price . ']]></price>';
        $ps_cat_xml .= '<minimal_quantity>0</minimal_quantity>';
        $ps_cat_xml .= '<associations>';
        $ps_cat_xml .= '<product_option_values>';
        $ps_cat_xml .= '<product_option_value><id>' . $ps_a1_id . '</id></product_option_value>';
        $ps_cat_xml .= '<product_option_value><id>' . $ps_a2_id . '</id></product_option_value>';
        $ps_cat_xml .= '<product_option_value><id>' . $ps_a3_id . '</id></product_option_value>';
        $ps_cat_xml .= '</product_option_values>';
        $ps_cat_xml .= '</associations>';
        $ps_cat_xml .= '</combination>';

        $ps_cat_xml .= '</prestashop>';

        echo $ps_cat_xml;


        $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $ps_api_endpoint_sku);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $ps_cat_xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/xml',
        'Authorization: Basic ' . base64_encode($ps_api_key . ':')
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    echo 'Returned XML: ';
    echo $result;


    curl_close($ch);

    // Retrieve <id> from result and insert into integration mapping table
    
    // If returned XML is blank, insert error and continue
    if ($result == '' | $result == null) {
        $ps_error_msg = 'No Response';
        continue;
    }


    $result_xml = new SimpleXMLElement($result);

    // Extract created ID value

    $ps_created_id = (string)$result_xml->combination->id;

    if ($ps_created_id == '' | $ps_created_id == null) {
        $ps_created_id = 'EXIT';
        $ps_error_msg = (string)$result_xml->errors->error->message;
    }


    $ps_cdb_id = $productSku['skuid'];
    if ($ps_created_id != 'EXIT') {
    $insertQuery = mysqli_query($mysqli, "INSERT into mapping (integration, value_1, value_2, value_3) VALUES ('PRESTASHOP', 'SKU', '$ps_cdb_id', '$ps_created_id')");
    } else {
        // Insert error message into mapping table
        $insertQuery = mysqli_query($mysqli, "INSERT into mapping (integration, value_1, value_2, value_3) VALUES ('PRESTASHOP', 'SKU', '$ps_cdb_id', '$ps_error_msg')");
    }
    }

} else {
    $cron_script_returned = 'No Product Variations to Create';
}


// Pick up any updated products, and PATCH header + combination prices

$selectQuery = mysqli_query($mysqli, "SELECT productid, styleno, description, brand, model, cost, rrp, dropship, supplier, prd_description, prd_def_colour, PRD_CAT_ID FROM productheader AS ph LEFT JOIN productcategory AS pc ON pc.PRD_STYLE_NO = ph.styleno WHERE ph.epos_update = '1' GROUP BY ph.styleno ORDER BY ph.productid ASC LIMIT 5");
if (mysqli_num_rows($selectQuery) > 0) {

    while ($product = mysqli_fetch_assoc($selectQuery)) {
        
        // Get PS Product ID of current product

        $cdb_master_prd_id = $product['productid'];
        $cdb_master_style = $product['styleno'];
        $cdb_master_rrp = $product['rrp'];

        $psPrdQry = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'PRODUCT' AND value_2 = '$cdb_master_prd_id'");
        $psPrdData = mysqli_fetch_assoc($psPrdQry);

        $ps_master_prd_id = $psPrdData['value_3'];

        // Get PS Manufacturer of current product

        // Translate product brand to brand ID
        $cdb_brand_desc = $product['brand'];
        $brandQuery = mysqli_query($mysqli, "SELECT brandid FROM brands WHERE branddesc = '$cdb_brand_desc'");
        if (mysqli_num_rows($brandQuery) > 0) {
        $brandData = mysqli_fetch_assoc($brandQuery);
        $cdb_brand_id = $brandData['brandid'];
        } else {
            $cdb_brand_id = 'NOBRAND';
        }

        if ($cdb_brand_id != 'NOBRAND') {
        $manufacturerQuery = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'BRAND' AND value_2 = '$cdb_brand_id'");
        $manufacturerData = mysqli_fetch_assoc($manufacturerQuery);

        $ps_brand_id = $manufacturerData['value_3'];
        } else {
            $ps_brand_id = 'NOBRAND';
        }

        // Get PS supplier of current product

        // Translate product supplier code to CDB supplier ID
        $cdb_supplier_code = $product['supplier'];
        $supplierQuery = mysqli_query($mysqli, "SELECT supplierid FROM supplier WHERE suppliercode = '$cdb_supplier_code'");
        if (mysqli_num_rows($supplierQuery) > 0) {
        $supplierData = mysqli_fetch_assoc($supplierQuery);
        $cdb_supplier_id = $supplierData['supplierid'];
        } else {
            $cdb_supplier_id = 'NOSUPPLIER';
        }

        if ($cdb_supplier_id != 'NOSUPPLIER') {
        $psSupplierQuery = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'SUPPLIER' AND value_2 = '$cdb_supplier_id'");
        $psSupplierData = mysqli_fetch_assoc($psSupplierQuery);

        $ps_supplier_id = $psSupplierData['value_3'];
        } else {
            $ps_supplier_id = 'NOSUPPLIER';
        }

        // Get PS category ID for default product category
        $cdb_def_category_id = $product['PRD_CAT_ID'];

        $psDefCatQuery = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'CATEGORY' AND value_2 = '$cdb_def_category_id'");
        if (mysqli_num_rows($psDefCatQuery) > 0) {
        $psDefCatData = mysqli_fetch_assoc($psDefCatQuery);
        $ps_def_category_id = $psDefCatData['value_3'];
        } else {
            $ps_def_category_id = 'NOCATEGORY';
        }
        // Is product online only?

        if ($product['dropship'] == 'Y') {
            $ps_online_only_flag = 1;
        } else {
            $ps_online_only_flag = 0;
        }

        // Build XML to send to PS

        $ps_cat_xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $ps_cat_xml .= '<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">';

        // Product Header Content


        $ps_cat_xml .= '<product>';
        $ps_cat_xml .= '<id>' . $ps_master_prd_id . '</id>';
        if ($ps_brand_id != 'NOBRAND') {
        $ps_cat_xml .= '<id_manufacturer>' . $ps_brand_id . '</id_manufacturer>';
        }
        if ($ps_supplier_id != 'NOSUPPLIER') {
        $ps_cat_xml .= '<id_supplier>' . $ps_supplier_id . '</id_supplier>';
        }
        if ($ps_def_category_id != 'NOCATEGORY') {
        $ps_cat_xml .= '<id_category_default>' . $ps_def_category_id . '</id_category_default>';
        }
        $ps_cat_xml .= '<type>0</type>';
        $ps_cat_xml .= '<reference>' . $product['styleno'] . '</reference>';
        $ps_cat_xml .= '<is_virtual>0</is_virtual>';
        $ps_cat_xml .= '<product_type>combinations</product_type>';
        $ps_cat_xml .= '<on_sale>0</on_sale>';
        $ps_cat_xml .= '<online_only>' . $ps_online_only_flag . '</online_only>';
        $ps_cat_xml .= '<price>' . $product['rrp'] . '</price>';
        $ps_cat_xml .= '<wholesale_price>' . $product['cost'] . '</wholesale_price>';
        $ps_cat_xml .= '<unit_price>' . $product['rrp'] . '</unit_price>';
        $ps_cat_xml .= '<customizable>0</customizable>';
        $ps_cat_xml .= '<active>1</active>';
        $ps_cat_xml .= '<available_for_order>1</available_for_order>';
        $ps_cat_xml .= '<condition>new</condition>';
        $ps_cat_xml .= '<visibility>both</visibility>';
        $ps_cat_xml .= '<show_price>1</show_price>';
        $ps_cat_xml .= '<state>1</state>';
        $ps_cat_xml .= '<name><language id="1">' . $product['description'] . '</language></name>';
        $ps_cat_xml .= '<description><language id="1">' . $product['prd_description'] . '</language></description>';
        $ps_cat_xml .= '<associations>';
        $ps_cat_xml .= '<categories nodeType="category" api="categories">';
        // While Loop through all product categories
        // Define current product style
        $cdb_current_style = $product['styleno'];
        $productCategoryQuery = mysqli_query($mysqli, "SELECT PRD_CAT_ID, value_3 FROM productcategory LEFT JOIN mapping ON productcategory.prd_cat_id = mapping.value_2 WHERE prd_style_no = '$cdb_current_style' AND integration = 'PRESTASHOP' AND value_1 = 'CATEGORY'");
            if (mysqli_num_rows($productCategoryQuery) > 0) {

                while ($productCategory = mysqli_fetch_assoc($productCategoryQuery)) {
                    // With current category, generate XML for association
                    $ps_cat_xml .= '<category><id>' . $productCategory['value_3'] . '</id></category>';
                }
            } else {
                // No categories to associate with product
            }
        $ps_cat_xml .= '</categories>';
        $ps_cat_xml .= '</associations>';
        $ps_cat_xml .= '</product>';

        $ps_cat_xml .= '</prestashop>';

        echo $ps_cat_xml;


        $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $ps_api_endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $ps_cat_xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/xml',
        'Authorization: Basic ' . base64_encode($ps_api_key . ':')
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    echo 'Returned XML: ';
    echo $result;


    curl_close($ch);

    $insertQuery = mysqli_query($mysqli, "UPDATE productheader SET epos_update = '0' WHERE productid = '$cdb_master_prd_id'");

    // Now send updated combinations for the current product

    $selectSkuQuery = mysqli_query($mysqli, "SELECT skuid, styleno, sku, ean1, mpn1, cost, rrp, colourid, fitid, sizingid FROM productline AS pl LEFT JOIN colours ON pl.colour = colours.colourcode LEFT JOIN fit ON pl.fit = fit.fitcode LEFT JOIN sizing ON pl.size = sizing.sizingcode WHERE styleno = '$cdb_master_style' GROUP BY pl.sku ORDER BY pl.skuid ASC LIMIT 1000;");
    if (mysqli_num_rows($selectQuery) > 0) {

    header('Content-Type: text/text');
    while ($productSku = mysqli_fetch_assoc($selectSkuQuery)) {

        // Build variables for use within XML request

        // Get combination ID

        $cdb_combination_id = $productSku['skuid'];

        $psCombQry = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'SKU' AND value_2 = '$cdb_combination_id'");
        $psCombData = mysqli_fetch_assoc($psCombQry);

        $ps_combination_master_id = $psCombData['value_3'];

        $ps_master_rrp = $cdb_master_rrp;

        $ps_sku_ean = $productSku['ean1'];

        // Check EAN has 13 digits, if not, do not include within XML

        if (strlen($ps_sku_ean) < 13 | strlen($ps_sku_ean) > 13) {
            $ps_sku_ean = 'NOINCLUDE';
        }

        // Check EAN is only digits, if not, do not include within XML

        if (is_numeric($ps_sku_ean) == False) {
            $ps_sku_ean = 'NOINCLUDE';
        }

        $ps_sku_mpn = $productSku['mpn1'];

        if (strlen($ps_sku_mpn) < 1) {
            $ps_sku_mpn = 'NOINCLUDE';
        }
        $ps_sku_reference = $productSku['sku'];
        $ps_wholesale = $productSku['cost'];

        // Calculate combination price

        $ps_combination_price = $productSku['rrp'] - $ps_master_rrp;

        // Get PS attribute 1, 2, 3 IDs

        $cdb_a1_id = $productSku['colourid'];
        $cdb_a2_id = $productSku['fitid'];
        $cdb_a3_id = $productSku['sizingid'];

        $psA1Query = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'A1' AND value_2 = '$cdb_a1_id'");
        $psA2Query = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'A2' AND value_2 = '$cdb_a2_id'");
        $psA3Query = mysqli_query($mysqli, "SELECT value_3 FROM mapping WHERE integration = 'PRESTASHOP' AND value_1 = 'A3' AND value_2 = '$cdb_a3_id'");

        $psA1Data = mysqli_fetch_assoc($psA1Query);
        $psA2Data = mysqli_fetch_assoc($psA2Query);
        $psA3Data = mysqli_fetch_assoc($psA3Query);

        $ps_a1_id = $psA1Data['value_3'];
        $ps_a2_id = $psA2Data['value_3'];
        $ps_a3_id = $psA3Data['value_3'];
        

        // Build XML to send to PS

        $ps_cat_xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $ps_cat_xml .= '<prestashop xmlns:xlink="http://www.w3.org/1999/xlink">';

        // Product Header Content


        $ps_cat_xml .= '<combination>';
        $ps_cat_xml .= '<id>' . $ps_combination_master_id . '</id>';
        $ps_cat_xml .= '<id_product>' . $ps_master_prd_id . '</id_product>';
        if ($ps_sku_ean != 'NOINCLUDE') {
        $ps_cat_xml .= '<ean13>' . $ps_sku_ean . '</ean13>';
        }
        if ($ps_sku_mpn != 'NOINCLUDE') {
        $ps_cat_xml .= '<mpn>' . $ps_sku_mpn . '</mpn>';
        }
        $ps_cat_xml .= '<reference>' . $ps_sku_reference . '</reference>';
        $ps_cat_xml .= '<wholesale_price>' . $ps_wholesale . '</wholesale_price>';
        $ps_cat_xml .= '<price><![CDATA[' . $ps_combination_price . ']]></price>';
        $ps_cat_xml .= '<minimal_quantity>0</minimal_quantity>';
        $ps_cat_xml .= '<associations>';
        $ps_cat_xml .= '<product_option_values>';
        $ps_cat_xml .= '<product_option_value><id>' . $ps_a1_id . '</id></product_option_value>';
        $ps_cat_xml .= '<product_option_value><id>' . $ps_a2_id . '</id></product_option_value>';
        $ps_cat_xml .= '<product_option_value><id>' . $ps_a3_id . '</id></product_option_value>';
        $ps_cat_xml .= '</product_option_values>';
        $ps_cat_xml .= '</associations>';
        $ps_cat_xml .= '</combination>';

        $ps_cat_xml .= '</prestashop>';

        echo $ps_cat_xml;


        $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $ps_api_endpoint_sku);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $ps_cat_xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/xml',
        'Authorization: Basic ' . base64_encode($ps_api_key . ':')
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    echo 'Returned XML: ';
    echo $result;


    curl_close($ch);

    
    // If returned XML is blank, insert error and continue
    if ($result == '' | $result == null) {
        $ps_error_msg = 'No Response';
        continue;
    }



    }
}
    }

} else {
    $cron_script_returned = ' No Products to Update';
}


    $cron_script_returned = $cron_script_returned . ' Controller Finished';

?>