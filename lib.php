<?php
class Products
{

    // XML tag => table header label
    const COLUMNS = [
        'NAME' => 'Name',
        'PRICE' => 'Price',
        'QUANTITY' => 'Quantity',
        'CATEGORY' => 'Category',
        'MANUFACTURER' => 'Manufacturer',
        'BARCODE' => 'Barcode',
        'WEIGHT' => 'Weight',
        'INSTOCK' => 'In stock',
        'AVAILABILITY' => 'Availability',
    ];

    private $xml_file_path = '';

    public function __construct($xml_file_path = '')
    {
        $this->xml_file_path = $xml_file_path;
    }

    /**
     * This function prints an HTML table with all the products as read from the xml file
     * @return void 
     */
    public function print_html_table_with_all_products()
    {
        //TODO 1:Θα πρέπει να συμπληρώσουμε την συνάρτηση ώστε να κάνει print το HTML table με τα προϊόντα του xml
        $xmldata = simplexml_load_file($this->xml_file_path) or die("Failed to load");
        $xml_data = $xmldata->children();

        echo '<table class="products">';
        echo '<thead><tr>';
        foreach (self::COLUMNS as $tag => $label) {
            echo '<th class="col-' . strtolower($tag) . '">' . htmlspecialchars($label) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($xml_data->PRODUCTS->PRODUCT as $key => $prod) {
            
            $this->print_html_of_one_product_line($prod);
        
        }
        
        echo '</tbody></table>';

    }

    /**
     * This function prints an HTML tr for a given product
     * @param mixed $prod It is the product object as retrieved from the xml file
     * @return void 
     */
    private function print_html_of_one_product_line($prod){
        //TODO 2: Θα πρέπει να συμπληρώσουμε τη συνάρτηση ώστε να κάνει print τα tr με τα στοιχεία του ενός προϊόντος
        //var_dump($prod);
        echo '<tr>';
        foreach (self::COLUMNS as $tag => $label) {
            echo '<td class="col-' . strtolower($tag) . '">' . htmlspecialchars(trim((string) $prod->$tag)) . '</td>';
        }
        echo '</tr>';
    }

    /**
     * This function validates the fields of a new product and normalizes PRICE and WEIGHT
     * @param array $data The trimmed product fields keyed by XML tag, updated in place
     * @return array The validation errors, empty if the product is valid
     */
    private function validate_product(&$data)
    {
        $errors = [];
        foreach (self::COLUMNS + ['CATEGORY_ID' => 'Category ID'] as $tag => $label) {
            $data[$tag] = $data[$tag] ?? '';
            // /u fails on invalid UTF-8, so this also rejects malformed input
            if (preg_match('/^[^\x00-\x1F\x7F]{0,255}$/u', $data[$tag]) !== 1) {
                $errors[] = $label . ' must be valid text of at most 255 characters.';
            }
        }
        if ($errors) {
            return $errors;
        }

        if ($data['NAME'] === '') {
            $errors[] = 'Name is required.';
        }

        if ($data['PRICE'] !== '') {
            $price = str_replace(',', '.', $data['PRICE']);
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
                $errors[] = 'Price must be a positive number with up to 2 decimals, e.g. 64.90.';
            } else {
                $data['PRICE'] = (string) (float) $price;
            }
        }

        if ($data['QUANTITY'] !== '' && !preg_match('/^\d{1,9}$/', $data['QUANTITY'])) {
            $errors[] = 'Quantity must be a whole number of 0 or more.';
        }

        if ($data['CATEGORY_ID'] !== '' && !preg_match('/^\d{1,18}$/', $data['CATEGORY_ID'])) {
            $errors[] = 'Category ID must contain only digits.';
        }

        // EAN-8, UPC-A, EAN-13 or GTIN-14
        if ($data['BARCODE'] !== '' && !preg_match('/^(\d{8}|\d{12,14})$/', $data['BARCODE'])) {
            $errors[] = 'Barcode must be 8, 12, 13 or 14 digits.';
        }

        // Stored like the existing products, e.g. "6.1kg"
        if ($data['WEIGHT'] !== '') {
            if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(kg|g)$/i', $data['WEIGHT'], $m) && (float) str_replace(',', '.', $m[1]) > 0) {
                $data['WEIGHT'] = (float) str_replace(',', '.', $m[1]) . strtolower($m[2]);
            } else {
                $errors[] = 'Weight must be a number above 0 followed by kg or g, e.g. 6.1kg or 500g.';
            }
        }

        if ($data['INSTOCK'] !== '' && !in_array($data['INSTOCK'], ['Y', 'N'], true)) {
            $errors[] = 'In stock must be Y or N.';
        }

        return $errors;
    }

    /**
     * This function validates a new product and appends it to the xml file
     * @param array $data The product fields keyed by XML tag (NAME is required), plus an optional CATEGORY_ID
     * @return array The validation errors, empty if the product was saved
     */
    public function add_product($data)
    {
        $data = array_map(fn($v) => is_string($v) ? trim($v) : '', is_array($data) ? $data : []);
        $errors = $this->validate_product($data);
        if ($errors) {
            return $errors;
        }

        // Lock the file so two submissions at once cannot overwrite each other
        $fp = fopen($this->xml_file_path, 'r+');
        if (!$fp) {
            return ['Could not open the products file.'];
        }
        flock($fp, LOCK_EX);

        $contents = stream_get_contents($fp);
        $doc = new DOMDocument();
        if (!$doc->loadXML($contents) || !($products = $doc->getElementsByTagName('PRODUCTS')->item(0))) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return ['Could not read the products file.'];
        }

        // Build the new PRODUCT with the same indentation as the existing ones
        $product = $doc->createElement('PRODUCT');
        foreach (self::COLUMNS as $tag => $label) {
            $value = $data[$tag] ?? '';
            $el = $doc->createElement($tag);
            // NAME, BARCODE and WEIGHT are stored as CDATA in the existing file
            if (in_array($tag, ['NAME', 'BARCODE', 'WEIGHT'], true) && strpos($value, ']]>') === false) {
                $el->appendChild($doc->createCDATASection($value));
            } else {
                $el->appendChild($doc->createTextNode($value));
            }
            if ($tag === 'CATEGORY' && ($data['CATEGORY_ID'] ?? '') !== '') {
                $el->setAttribute('id', $data['CATEGORY_ID']);
            }
            $product->appendChild($doc->createTextNode("\n            "));
            $product->appendChild($el);
        }
        $product->appendChild($doc->createTextNode("\n        "));

        $last = $products->lastChild;
        if ($last && $last->nodeType === XML_TEXT_NODE) {
            $products->insertBefore($doc->createTextNode("\n        "), $last);
            $products->insertBefore($product, $last);
        } else {
            $products->appendChild($product);
        }

        $last_update = $doc->getElementsByTagName('LAST_UPDATE')->item(0);
        if ($last_update) {
            $last_update->textContent = date('Y-m-d H:i');
        }

        // DOMDocument writes LF line endings, so keep the file's CRLF if it had them
        $output = $doc->saveXML();
        if (strpos($contents, "\r\n") !== false) {
            $output = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $output));
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $output);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return [];
    }
}
