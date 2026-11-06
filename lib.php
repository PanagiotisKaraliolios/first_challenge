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
     * This function validates a new product and appends it to the xml file
     * @param array $data The product fields keyed by XML tag (NAME is required), plus an optional CATEGORY_ID
     * @return array The validation errors, empty if the product was saved
     */
    public function add_product($data)
    {
        $data = array_map(fn($v) => is_string($v) ? trim($v) : '', is_array($data) ? $data : []);
        $errors = [];
        if (($data['NAME'] ?? '') === '') {
            $errors[] = 'Name is required.';
        }
        if (($data['PRICE'] ?? '') !== '' && !is_numeric($data['PRICE'])) {
            $errors[] = 'Price must be a number.';
        }
        if (($data['QUANTITY'] ?? '') !== '' && !ctype_digit($data['QUANTITY'])) {
            $errors[] = 'Quantity must be a whole number.';
        }
        if (($data['INSTOCK'] ?? '') !== '' && !in_array($data['INSTOCK'], ['Y', 'N'], true)) {
            $errors[] = 'In stock must be Y or N.';
        }
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
