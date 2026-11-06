<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Products</title>
<link rel="stylesheet" href="./style.css">
</head>
<body>

<h1>List of products</h1>

<div class="table-wrap">
<?php
    require_once("./lib.php");
    $productsList = new Products("./products.xml");
    $productsList->print_html_table_with_all_products();

?>
</div>

</body>
</html>
