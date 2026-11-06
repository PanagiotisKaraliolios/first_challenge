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
}
