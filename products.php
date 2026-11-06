<?php
    require_once("./lib.php");
    $productsList = new Products("./products.xml");

    $errors = [];
    $form = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = is_array($_POST['product'] ?? null) ? $_POST['product'] : [];
        $errors = $productsList->add_product($form);
        if (!$errors) {
            // Redirect so a page refresh does not submit the product again
            header('Location: ' . basename($_SERVER['PHP_SELF']) . '?added=1');
            exit;
        }
    }

    // Value of a form field, escaped for HTML
    function field($form, $name)
    {
        return htmlspecialchars(is_string($form[$name] ?? null) ? $form[$name] : '');
    }
?>
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

<?php if (isset($_GET['added'])): ?>
<p class="notice success">Product added.</p>
<?php endif; ?>

<div class="table-wrap">
<?php
    $productsList->print_html_table_with_all_products();

?>
</div>

<h2>Add a product</h2>

<?php if ($errors): ?>
<div class="notice error">
    <?php foreach ($errors as $error): ?>
    <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form class="product-form" method="post" action="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>">
    <label class="wide">Name *
        <input type="text" name="product[NAME]" value="<?= field($form, 'NAME') ?>" required>
    </label>
    <label>Price
        <input type="number" name="product[PRICE]" value="<?= field($form, 'PRICE') ?>" step="0.01" min="0">
    </label>
    <label>Quantity
        <input type="number" name="product[QUANTITY]" value="<?= field($form, 'QUANTITY') ?>" step="1" min="0">
    </label>
    <label>Category
        <input type="text" name="product[CATEGORY]" value="<?= field($form, 'CATEGORY') ?>">
    </label>
    <label>Category ID
        <input type="text" name="product[CATEGORY_ID]" value="<?= field($form, 'CATEGORY_ID') ?>">
    </label>
    <label>Manufacturer
        <input type="text" name="product[MANUFACTURER]" value="<?= field($form, 'MANUFACTURER') ?>">
    </label>
    <label>Barcode
        <input type="text" name="product[BARCODE]" value="<?= field($form, 'BARCODE') ?>">
    </label>
    <label>Weight
        <input type="text" name="product[WEIGHT]" value="<?= field($form, 'WEIGHT') ?>" placeholder="e.g. 6.1kg">
    </label>
    <label>In stock
        <select name="product[INSTOCK]">
            <option value="Y"<?= field($form, 'INSTOCK') === 'N' ? '' : ' selected' ?>>Y</option>
            <option value="N"<?= field($form, 'INSTOCK') === 'N' ? ' selected' : '' ?>>N</option>
        </select>
    </label>
    <label>Availability
        <input type="text" name="product[AVAILABILITY]" value="<?= field($form, 'AVAILABILITY') ?>">
    </label>
    <div class="wide">
        <button type="submit">Add product</button>
    </div>
</form>

</body>
</html>
