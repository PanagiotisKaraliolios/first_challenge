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
        $xmldata = $this->quietly('products.xml', fn() => simplexml_load_string($this->read_products_file()));
        // A message instead of die(), so the rest of the page, including any save error and the form, still appears
        if (!$xmldata) {
            echo '<p class="notice error">Failed to load the products file.</p>';
            return;
        }
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
            $value = trim((string) $prod->$tag);
            if ($tag === 'PRICE') {
                $value = $this->format_price($value);
            }
            echo '<td class="col-' . strtolower($tag) . '">' . htmlspecialchars($value) . '</td>';
        }
        echo '</tr>';
    }

    /**
     * This function reads the xml file while holding a shared lock, so it never sees a save half-way through
     * (on Windows a read during a save would otherwise fail, because file locks there are mandatory)
     * @return string The file contents, or an empty string if the file cannot be read
     */
    private function read_products_file()
    {
        $fp = @fopen($this->xml_file_path, 'r');
        if (!$fp) {
            return '';
        }
        flock($fp, LOCK_SH);
        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return is_string($contents) ? $contents : '';
    }

    /**
     * This function runs a libxml call without printing its warnings into the page and logs them instead
     * @param string $label The prefix for the logged messages
     * @param callable $callback The libxml call
     * @return mixed What the callback returns
     */
    private function quietly($label, $callback)
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $result = $callback();
        foreach (libxml_get_errors() as $error) {
            error_log($label . ': ' . trim($error->message));
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $result;
    }

    /**
     * This function formats a price in euro the Greek way, e.g. 64.9 becomes "64,90 €"
     * @param string $price The price as stored in the xml file
     * @return string The formatted price, or the value unchanged if it is not a plain decimal number
     */
    private function format_price($price)
    {
        // Longer numbers would lose digits in the float conversion, so they are shown as stored
        if (!preg_match('/^\d{1,12}(\.\d{1,2})?$/', $price)) {
            return $price;
        }
        return number_format((float) $price, 2, ',', '.') . ' €';
    }

    /**
     * This function writes a decimal number without extra zeros, e.g. "006,50" becomes "6.5"
     * @param string $number Digits with an optional "." or "," decimal part
     * @return string The normalized number
     */
    private function normalize_decimal($number)
    {
        $parts = explode('.', str_replace(',', '.', $number), 2);
        $whole = ltrim($parts[0], '0');
        $fraction = rtrim($parts[1] ?? '', '0');
        return ($whole === '' ? '0' : $whole) . ($fraction === '' ? '' : '.' . $fraction);
    }

    /**
     * This function validates the fields of a new product, normalizes PRICE, QUANTITY and WEIGHT
     * and sets INSTOCK from QUANTITY when QUANTITY is given
     * @param array $data The trimmed product fields keyed by XML tag, updated in place
     * @return array The validation errors, empty if the product is valid
     */
    private function validate_product(&$data)
    {
        $errors = [];
        foreach (self::COLUMNS + ['CATEGORY_ID' => 'Category ID'] as $tag => $label) {
            $data[$tag] = $data[$tag] ?? '';
            // /u fails on invalid UTF-8, so this also rejects malformed input.
            // Control characters and U+FFFE/U+FFFF are refused because XML does not allow them
            if (preg_match('/^[^\x00-\x1F\x7F-\x{9F}\x{FFFE}\x{FFFF}]{0,255}$/u', $data[$tag]) !== 1) {
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
            if (preg_match('/^\d{1,9}([.,]\d{1,2})?$/', $data['PRICE'])) {
                $data['PRICE'] = $this->normalize_decimal($data['PRICE']);
            } else {
                $errors[] = 'Price must be a number of 0 or more with up to 2 decimals, e.g. 64.90.';
            }
        }

        if ($data['QUANTITY'] !== '') {
            if (preg_match('/^\d{1,9}$/', $data['QUANTITY'])) {
                $data['QUANTITY'] = (string) (int) $data['QUANTITY'];
                // A known quantity decides whether the product is in stock
                $data['INSTOCK'] = $data['QUANTITY'] === '0' ? 'N' : 'Y';
            } else {
                $errors[] = 'Quantity must be a whole number of 0 or more.';
            }
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
            if (preg_match('/^(\d{1,6}(?:[.,]\d{1,3})?)\s*(kg|g)$/i', $data['WEIGHT'], $m) && (float) str_replace(',', '.', $m[1]) > 0) {
                $data['WEIGHT'] = $this->normalize_decimal($m[1]) . strtolower($m[2]);
            } else {
                $errors[] = 'Weight must be a number above 0 with up to 3 decimals, followed by kg or g, e.g. 6.1kg or 500g.';
            }
        }

        if ($data['INSTOCK'] !== '' && !in_array($data['INSTOCK'], ['Y', 'N'], true)) {
            $errors[] = 'In stock must be Y or N.';
        }

        return $errors;
    }

    /**
     * This function finds existing products with the same name (ignoring case) or barcode as a new product
     * @param DOMDocument $doc The loaded xml file
     * @param array $data The validated product fields
     * @return array A message for each match, empty if there are none
     */
    private function find_duplicates($doc, $data)
    {
        // Drop invisible format characters such as zero-width spaces and collapse all whitespace before comparing
        $normalize = fn($text) => trim(preg_replace(['/\p{Cf}/u', '/\s+/u'], ['', ' '], $text));
        $new_name = $normalize($data['NAME']);
        $duplicates = [];
        foreach ($doc->getElementsByTagName('PRODUCT') as $product) {
            $name = $normalize($product->getElementsByTagName('NAME')->item(0)?->textContent ?? '');
            $barcode = trim($product->getElementsByTagName('BARCODE')->item(0)?->textContent ?? '');
            // The u and i flags also ignore case for Greek letters, which strcasecmp() cannot
            if (preg_match('/^' . preg_quote($new_name, '/') . '$/iu', $name)) {
                $duplicates[] = 'A product named "' . $name . '" already exists.';
            }
            if ($data['BARCODE'] !== '' && $barcode === $data['BARCODE']) {
                $duplicates[] = 'Barcode ' . $barcode . ' is already used by "' . $name . '".';
            }
        }
        return $duplicates;
    }

    /**
     * This function parses xml text and logs the reasons when it is not well-formed
     * @param string|false $xml The xml text
     * @return DOMDocument|null The parsed document, or null if the text is not well-formed xml
     */
    private function parse_xml($xml)
    {
        // loadXML() throws on an empty string instead of returning false
        if (!is_string($xml) || $xml === '') {
            return null;
        }
        $doc = new DOMDocument();
        return $this->quietly('products.xml', fn() => $doc->loadXML($xml)) ? $doc : null;
    }

    /**
     * This function validates a new product and appends it to the xml file
     * @param array $data The product fields keyed by XML tag (NAME is required), plus an optional CATEGORY_ID
     * @param bool $allow_duplicates Save the product even if its name or barcode already exists
     * @return array ['errors' => [...], 'duplicates' => [...]], both empty if the product was saved
     */
    public function add_product($data, $allow_duplicates = false)
    {
        // Trim all whitespace, including non-breaking spaces, and invisible format characters such as zero-width spaces,
        // so a name made only of them counts as empty. Invalid UTF-8 is kept as is so validation reports it
        $trim = fn($v) => is_string($v) ? (preg_replace('/^[\s\p{Cf}]+|[\s\p{Cf}]+$/u', '', $v) ?? $v) : '';
        $data = array_map($trim, is_array($data) ? $data : []);
        $errors = $this->validate_product($data);
        if ($errors) {
            return ['errors' => $errors, 'duplicates' => []];
        }

        // Lock the file so two submissions at once cannot overwrite each other
        $fp = fopen($this->xml_file_path, 'r+');
        if (!$fp) {
            return ['errors' => ['Could not open the products file.'], 'duplicates' => []];
        }
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return ['errors' => ['Could not lock the products file.'], 'duplicates' => []];
        }
        try {
            return $this->write_product($fp, $data, $allow_duplicates);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * This function appends a validated product to the xml file
     * @param resource $fp The xml file, open for reading and writing and locked
     * @param array $data The validated product fields
     * @param bool $allow_duplicates Save the product even if its name or barcode already exists
     * @return array ['errors' => [...], 'duplicates' => [...]], both empty if the product was saved
     */
    private function write_product($fp, $data, $allow_duplicates)
    {
        $contents = stream_get_contents($fp);
        $doc = $this->parse_xml($contents);
        if (!$doc || !($products = $doc->getElementsByTagName('PRODUCTS')->item(0))) {
            return ['errors' => ['Could not read the products file.'], 'duplicates' => []];
        }

        if (!$allow_duplicates) {
            $duplicates = $this->find_duplicates($doc, $data);
            if ($duplicates) {
                return ['errors' => [], 'duplicates' => $duplicates];
            }
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

        // Check that the exact text that will be written can be read back, so a save can never leave an unreadable file
        if (!$this->parse_xml($output)) {
            return ['errors' => ['The product was not saved because the products file would become unreadable.'], 'duplicates' => []];
        }

        ftruncate($fp, 0);
        rewind($fp);
        if (fwrite($fp, $output) !== strlen($output) || !fflush($fp)) {
            // Put the previous version back so the page keeps working
            $restored = ftruncate($fp, 0) && rewind($fp) && fwrite($fp, $contents) === strlen($contents) && fflush($fp);
            return ['errors' => [$restored
                ? 'Saving the products file failed, so the product was not saved.'
                : 'Saving the products file failed and the previous version could not be written back, so products.xml may be incomplete.'
            ], 'duplicates' => []];
        }

        return ['errors' => [], 'duplicates' => []];
    }
}
