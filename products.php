<?php
    require_once("./lib.php");

    // The session keeps a secret token that proves a submission came from this page's form (CSRF protection)
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    $productsList = new Products("./products.xml");

    $errors = [];
    $form = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = is_array($_POST['product'] ?? null) ? $_POST['product'] : [];
        $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $errors = ['The form has expired. Please submit it again.'];
        } else {
            $errors = $productsList->add_product($form);
            if (!$errors) {
                // Redirect so a page refresh does not submit the product again
                header('Location: ' . basename($_SERVER['PHP_SELF']) . '?added=1');
                exit;
            }
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
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label class="wide">Name *
        <input type="text" name="product[NAME]" value="<?= field($form, 'NAME') ?>" maxlength="255" required>
    </label>
    <label>Price (€)
        <input type="number" name="product[PRICE]" value="<?= field($form, 'PRICE') ?>" step="0.01" min="0" max="999999999.99">
    </label>
    <label>Quantity
        <input type="number" name="product[QUANTITY]" value="<?= field($form, 'QUANTITY') ?>" step="1" min="0" max="999999999">
    </label>
    <label>Category
        <input type="text" name="product[CATEGORY]" value="<?= field($form, 'CATEGORY') ?>" maxlength="255">
    </label>
    <label>Category ID
        <input type="text" name="product[CATEGORY_ID]" value="<?= field($form, 'CATEGORY_ID') ?>" inputmode="numeric" pattern="\d{1,18}" title="Digits only">
    </label>
    <label>Manufacturer
        <input type="text" name="product[MANUFACTURER]" value="<?= field($form, 'MANUFACTURER') ?>" maxlength="255">
    </label>
    <label>Barcode
        <input type="text" name="product[BARCODE]" value="<?= field($form, 'BARCODE') ?>" inputmode="numeric" pattern="\d{8}|\d{12,14}" title="8, 12, 13 or 14 digits">
    </label>
    <label>Weight
        <input type="text" name="product[WEIGHT]" value="<?= field($form, 'WEIGHT') ?>" pattern="\d{1,6}([.,]\d{1,3})?\s*([kK][gG]|[gG])" title="A number with up to 3 decimals followed by kg or g, e.g. 6.1kg or 500g" placeholder="e.g. 6.1kg">
    </label>
    <label>In stock
        <select name="product[INSTOCK]">
            <option value="Y"<?= field($form, 'INSTOCK') === 'N' ? '' : ' selected' ?>>Y</option>
            <option value="N"<?= field($form, 'INSTOCK') === 'N' ? ' selected' : '' ?>>N</option>
        </select>
        <small>Set from Quantity when Quantity is filled in.</small>
    </label>
    <label>Availability
        <input type="text" name="product[AVAILABILITY]" value="<?= field($form, 'AVAILABILITY') ?>" maxlength="255">
    </label>
    <div class="wide">
        <button type="submit">Add product</button>
    </div>
</form>

<script>
// Show In stock as the server will save it: Y when Quantity is above 0, N when it is 0
(function () {
    var quantity = document.querySelector('[name="product[QUANTITY]"]');
    var instock = document.querySelector('[name="product[INSTOCK]"]');
    function sync() {
        var filled = quantity.value.trim() !== '';
        instock.disabled = filled;
        if (filled) {
            instock.value = Number(quantity.value) > 0 ? 'Y' : 'N';
        }
    }
    quantity.addEventListener('input', sync);
    sync();
})();
</script>

</body>
</html>
