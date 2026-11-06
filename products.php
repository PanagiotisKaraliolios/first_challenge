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
    $duplicates = [];
    $form = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form = is_array($_POST['product'] ?? null) ? $_POST['product'] : [];
        $token = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $errors = ['The form has expired. Please submit it again.'];
        } else {
            $result = $productsList->add_product($form, ($_POST['allow_duplicates'] ?? '') === '1');
            $errors = $result['errors'];
            $duplicates = $result['duplicates'];
            if (!$errors && !$duplicates) {
                // Redirect so a page refresh does not submit the product again
                header('Location: products.php?added=1');
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

<?php if ($duplicates): ?>
<div class="notice warning">
    <?php foreach ($duplicates as $duplicate): ?>
    <p><?= htmlspecialchars($duplicate) ?></p>
    <?php endforeach; ?>
    <p>Tick "Add it anyway" and submit again to save it as a new product.</p>
</div>
<?php endif; ?>

<?php // A fixed action: one built from PHP_SELF would let a crafted URL such as products.php/javascript:... set it ?>
<?php
    // pattern, maxlength and required repeat the rules in Products::validate_product() so mistakes show before submitting.
    // The server is the check that counts; change both places together. Browsers match a pattern against the whole value,
    // so it needs no ^ and $, and the \s* around each pattern allows the spaces that add_product() trims
?>
<form class="product-form" method="post" action="products.php">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <label class="wide">Name *
        <input type="text" name="product[NAME]" value="<?= field($form, 'NAME') ?>" maxlength="255" required>
    </label>
    <label>Price (€)
        <input type="text" name="product[PRICE]" value="<?= field($form, 'PRICE') ?>" inputmode="decimal" pattern="\s*\d{1,9}([.,]\d{1,2})?\s*" title="A number with up to 2 decimals, e.g. 64.90" placeholder="e.g. 64.90">
    </label>
    <label>Quantity
        <input type="text" name="product[QUANTITY]" value="<?= field($form, 'QUANTITY') ?>" inputmode="numeric" pattern="\s*\d{1,9}\s*" title="A whole number of 0 or more">
    </label>
    <label>Category
        <input type="text" name="product[CATEGORY]" value="<?= field($form, 'CATEGORY') ?>" maxlength="255">
    </label>
    <label>Category ID
        <input type="text" name="product[CATEGORY_ID]" value="<?= field($form, 'CATEGORY_ID') ?>" inputmode="numeric" pattern="\s*\d{1,18}\s*" title="Digits only">
    </label>
    <label>Manufacturer
        <input type="text" name="product[MANUFACTURER]" value="<?= field($form, 'MANUFACTURER') ?>" maxlength="255">
    </label>
    <label>Barcode
        <input type="text" name="product[BARCODE]" value="<?= field($form, 'BARCODE') ?>" inputmode="numeric" pattern="\s*(\d{8}|\d{12,14})\s*" title="8, 12, 13 or 14 digits">
    </label>
    <?php
        // Same rule as the WEIGHT check in Products::validate_product(). The lookahead requires a non-zero digit, like the
        // server's "> 0" check. " *" allows only spaces before the unit: the server's \s has no /u flag, so it does not match
        // a non-breaking space. [kK][gG] replaces the /i flag, which a pattern attribute cannot use
    ?>
    <label>Weight
        <input type="text" name="product[WEIGHT]" value="<?= field($form, 'WEIGHT') ?>" pattern="\s*(?=[\d.,]*[1-9])\d{1,6}([.,]\d{1,3})? *([kK][gG]|[gG])\s*" title="A number above 0 with up to 3 decimals followed by kg or g, e.g. 6.1kg or 500g" placeholder="e.g. 6.1kg">
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
    <?php if ($duplicates): ?>
    <label class="wide confirm">
        <input type="checkbox" name="allow_duplicates" value="1"> Add it anyway
    </label>
    <?php endif; ?>
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
