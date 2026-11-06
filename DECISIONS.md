# Decisions and changes

This file records what was built for the challenge in `README.md`, the decisions behind it and the changes made along the way.

The challenge asked for two things:

1. Print an HTML table with every product in `products.xml`.
2. Add a form that saves a new product to `products.xml`, with `NAME` required.

Both are done. On top of them the project gained styling, field validation and a few small extra features, followed by two rounds of review. The extras are listed separately below so the required work stays easy to find.

## Running it

- PHP 8 with the default extensions (`dom`, `libxml`, `SimpleXML`, `session`, `pcre`). `mbstring` and `intl` are not needed.
- From the project folder: `php -S localhost:8000`, then open `http://localhost:8000/products.php`.
- Set `date.timezone` in `php.ini` (for example `Europe/Athens`). Without it PHP uses UTC, so `LAST_UPDATE` is written in UTC.

## Commits

| Commit | What it does |
|---|---|
| `c5aaf43` | Task 1: print the products table (plain HTML, no styling) |
| `04b411f` | Styling for the table |
| `8b69f56` | Task 2: form that saves a new product to the XML |
| `0cba910` | Validation of every field |
| `fc6cbbd` | Extra: prices shown in euro |
| `42fc726` | Extra: In stock follows Quantity |
| `04b6790` | Extra: CSRF protection for the form |
| `074ae0a` | Fix: prices and weights stored without scientific notation |
| `873ed49` | Extra: warning before adding a duplicate product |
| `3e1335d` | Fixes from the two review rounds |

Each task was committed on its own, before any extra work, so the minimum answer to each task can be read in isolation. Each extra feature is one commit as it was first written; everything the reviews found is fixed together in the last commit.

## Task 1: products table

- **Column list in one place.** `Products::COLUMNS` maps each XML tag to its header label, in the order the tags appear in the XML. The header row and every product row are printed from it, so a column is added or removed in one line.
- **Values are trimmed.** The XML pads values with newlines and indentation (for example `PRICE`), so each value goes through `trim()`.
- **Values are escaped.** Every value goes through `htmlspecialchars()`. Product names contain `"` and the category contains `->`, so unescaped output would already be wrong with the sample data.
- **CDATA fields.** `NAME`, `BARCODE` and `WEIGHT` are CDATA sections. `(string) $prod->NAME` returns their text. The `var_dump($prod)` hint left in the code shows them as empty objects, which is a `var_dump` limitation, not missing data.
- **Category id.** The `id` attribute of `CATEGORY` is not shown in the table. It is an internal code, and the category name is what a reader needs.
- **The given code was kept where possible.** The two TODO functions were filled in rather than replaced, and the original comments, including `//var_dump($prod);`, are untouched. Two lines of the given code were changed later, both for review findings:
  - The file is read under a shared lock (see "Saving safely").
  - The given `or die("Failed to load")` became a "Failed to load the products file." notice, so the rest of the page, including the form and any save error, still renders when the file cannot be read.

### Styling (separate commit)

- `style.css` holds all styling, linked from `products.php`. The page has a `<head>` with UTF-8, a viewport tag and a title.
- The table has `thead`/`tbody` and a `col-<tag>` class on every cell, so columns can be styled individually: numbers are right-aligned, the barcode uses a monospace font, and so on.
- The table sits in a horizontally scrolling wrapper so it stays usable on narrow screens.

## Task 2: adding a product

- **The form is on `products.php`, below the table.** The README allowed either a new page or the same page. One page means the new product is visible as soon as it is saved.
- **The saving function is `Products::add_product()`**, as the README suggested.
- **DOMDocument for writing, not SimpleXML.** SimpleXML cannot create CDATA sections or control whitespace. DOMDocument can, so a new product is written with the same tags, order, indentation and CDATA usage as the existing ones. The rest of the file is left byte-for-byte unchanged, including its Windows (CRLF) line endings, so a diff of `products.xml` shows only the new product and `LAST_UPDATE`.
- **CDATA fallback.** A value containing `]]>` cannot go inside CDATA, so such a value is written as an escaped text node instead.
- **Every product has every tag.** Optional fields left empty are written as empty elements, so all products keep the same structure.
- **`LAST_UPDATE` is refreshed** on every save.
- **The file is locked while it is rewritten** (`flock` with an exclusive lock around read, change and write), so two simultaneous submissions cannot overwrite each other.
- **Post/Redirect/Get.** After a successful save the page redirects to `products.php?added=1` and shows "Product added." Refreshing the page therefore does not add the product again.
- **The form keeps what was typed** when there are errors, and the errors are listed above it.
- **`NAME` is required** both in the browser (`required`) and on the server, which is the check that counts.

## Validation

The server is authoritative. The form repeats the same rules (`pattern`, `maxlength`, `required`) only to catch mistakes before submitting. The rules live in `Products::validate_product()`.

| Field | Rule | Stored as |
|---|---|---|
| All text fields | Valid UTF-8, at most 255 characters, no control characters and no characters XML forbids (U+FFFE, U+FFFF) | trimmed of spaces (including non-breaking ones) and invisible characters such as zero-width spaces |
| Name | Required | |
| Price | 0 or more, up to 9 digits and 2 decimals, `.` or `,` accepted | `12,50` becomes `12.5` |
| Quantity | Whole number, 0 or more, up to 9 digits | `007` becomes `7` |
| Category ID | Digits only | |
| Barcode | 8, 12, 13 or 14 digits (EAN-8, UPC-A, EAN-13, GTIN-14) | |
| Weight | Number above 0, up to 6 digits and 3 decimals, then `kg` or `g` | `6,10 KG` becomes `6.1kg` |
| In stock | `Y` or `N` | set from Quantity when Quantity is given |

Decisions behind the rules:

- **Normalize to the existing format.** The sample data stores `64.9` and `6.1kg`, so input is converted to that format rather than stored as typed.
- **No float conversions when storing numbers.** The first version cast prices and weights to float, which turns very small or very large numbers into scientific notation (`1.0E-5kg`). They are now normalized as text (leading and trailing zeros removed), and the digit limits keep them within a sensible range. This was fixed in its own commit.
- **Weight units are limited to `kg` and `g`**, the units used in the sample data.
- **Barcode check digits are not verified**, only the length. The sample barcode does have a valid EAN-13 check digit, so this could be added later.
- **Price and Quantity are plain text inputs** with `inputmode` and a `pattern`, not `type="number"`. Number inputs accept values such as `1e2` or `-0` that the server rejects. With text inputs the browser and the server follow exactly the same rule.
- **Browser and server agree.** The patterns allow spaces around a value, because the server trims them, and the weight pattern rejects a zero weight, as the server does. This was checked by compiling every pattern the way browsers do and running the same sample values through both: nothing passes the browser and then fails on the server.
- **An empty-looking name is empty.** A name made only of spaces or invisible characters is trimmed to nothing and gets "Name is required."

## Extras beyond the brief

These were added after both tasks were done, in this order. Each one is small; the aim was to round off the form without growing the project far beyond the challenge.

### Prices in euro

Prices are displayed the Greek way, `64,90 €`, using `number_format()`. Only the display changes: the XML keeps `64.9`. Only plain prices with up to 12 digits and 2 decimals are formatted. Anything else, which can only come from editing the file by hand, is shown exactly as stored: longer numbers would lose digits in the float conversion, more decimals would be rounded away, and a malformed value should stay visible rather than be turned into something else.

### In stock follows Quantity

When Quantity is filled in, the server sets In stock from it: `N` for 0, `Y` above 0. This removes contradictions such as quantity 0 marked as in stock. When Quantity is empty the In stock choice is kept, for products whose quantity is unknown. A small script disables the In stock list while Quantity has a value and shows what will be saved. The server does the real work, so the rule holds without JavaScript too.

### CSRF protection

Without it, any other website could make a visitor's browser submit products to this page.

- A random token (`random_bytes(32)`) is stored in the session and placed in a hidden form field.
- The server compares them with `hash_equals()`, which takes the same time whether or not they match. A missing or wrong token gives "The form has expired. Please submit it again." and nothing is saved.
- The session cookie is `HttpOnly` and `SameSite=Lax`, a second layer that stops cross-site POSTs from carrying the cookie.
- One token per session, not one per request: it is simpler and enough here, because the token never appears in URLs.

### Duplicate warning

- A new product whose name matches an existing one (ignoring case, repeated spaces and invisible characters such as zero-width spaces) or whose barcode is already used gets a warning, and is not saved yet.
- The warning names the existing product, and an "Add it anyway" checkbox lets the user save it regardless.
- **Warn, do not block.** The two sample products already share barcode `5056245544934`, so a hard uniqueness rule would contradict the existing data.
- Case is ignored with PCRE's `/iu` flags, which handle Greek letters as well as Latin ones. This avoids depending on the `mbstring` extension, which is not loaded by default on Windows.
- Accents are not ignored: `ΜΑΎΡΟ` matches `μαύρο`, but `ΜΑΥΡΟ` (capitals written without the accent) does not.

### Dropped: schema check and backups

A sixth extra was built and then removed: an XSD schema for `products.xml` checked before every save, plus a copy of the file in a `backups/` folder before each write. It worked, but it was by far the largest addition (over 200 lines and a new schema file) and caused most of the review findings. It was too much for a challenge of this size, so it was taken out. The smaller safeguards that came out of that work stayed (see "Saving safely").

## Saving safely

These came out of the reviews and protect `products.xml`, which is the only copy of the data.

- Before writing, the exact text that will be written is parsed again. If it would not be readable XML, nothing is written.
- If writing fails part-way, the previous contents are written back.
- The table reads the file under a shared lock. On Windows file locks are mandatory, so without it a page view during a save failed to read the file. With the lock it waits for the save to finish.
- An empty or malformed `products.xml` gives "Could not read the products file." instead of a crash.
- libxml warnings (for example from a broken file) are logged with `error_log()` instead of being printed into the page, where they would expose server paths.
- **Not atomic.** The file is rewritten in place. If the disk fails during the write and writing the old contents back fails as well (for example, a full disk), `products.xml` can be left incomplete, and there is no backup copy. The complete fix is to write a temporary file and rename it over `products.xml`, using a separate lock file because Windows cannot rename over an open file. It was left out to keep the scope of the challenge in check.

## Review

After the extras were written, the changes were reviewed for security, data integrity and behavior. Every reported problem was reproduced before it was fixed; problems that could not be reproduced were not acted on. A second round re-ran every reproduction against the fixes and looked for problems the fixes introduced.

### First round

14 problems were confirmed (12 distinct). Four of them concerned only the schema check and backups, and went away with that feature. The others:

| Problem | Fix |
|---|---|
| A name containing U+FFFE or U+FFFF passed validation and was written to the file, which XML forbids. Every later page view failed, and PHP warnings showed server paths. | These characters (and C1 control characters) are rejected, and the text to be written is parsed again before the file is touched. |
| The form's `action` was built from `PHP_SELF` (code from task 2), so a link like `products.php/javascript:...` turned the submit button into a script that could read the CSRF token. | The form action and the redirect are fixed to `products.php`. |
| A failed write left `products.xml` truncated. | The previous contents are written back (see the limitation in "Saving safely"). |
| On Windows a page view during a save could not read the file. | The table reads under a shared lock. |
| An empty `products.xml` crashed the save with an uncaught error. | Empty or malformed files return "Could not read the products file." |
| The browser accepted number formats the server rejected (`1e2`, `-0`, `1.0`, a non-breaking space in the weight). | Text inputs with patterns equal to the server rules. |
| A name with a non-breaking space at either end escaped the duplicate check. | Unicode whitespace is trimmed and collapsed. |
| Very long prices were displayed with wrong digits, and `1e400` as `inf €`. | Only plain decimals are formatted. |

Two reports were not confirmed:

- A missing schema file, or an already-invalid `products.xml`, stopped every save with a generic message. Refusing to save is intended there, so this was judged a wording issue rather than a defect; it no longer applies since the schema check was dropped.
- Greek names typed without accents do not match accented ones. This is how the duplicate check is designed to work (see "Duplicate warning").

### Second round

The second round confirmed the first-round fixes held, with these exceptions and new findings, all fixed:

| Problem | Fix |
|---|---|
| When the file could not be read, the given `or die()` stopped the page at the table, so save errors and the form never appeared. | A notice is shown and the page keeps rendering. |
| Values with a space before or after them were blocked by the browser even though the server trims them. | The patterns allow surrounding spaces. |
| A zero weight passed the browser and failed on the server. | The weight pattern requires a non-zero digit. |
| Zero-width characters (U+200B, U+FEFF) at the ends of a name escaped the duplicate check, and a name made only of them was not treated as empty. | They are trimmed, and ignored when names are compared. |
| Prices with many decimals still went through the float conversion. | Only prices with up to 2 decimals are formatted. |

One problem is documented instead of fixed: the write is not atomic (see "Saving safely").

## Environment notes

- PHP 8.4 was installed with `winget` for local testing.
- A `php.ini` next to `php.exe` sets `date.timezone = Europe/Athens`. It is a machine setting, not part of the repository.
- Testing was done with PHP scripts run against copies of the project, never against the real `products.xml`. The scripts are not part of the repository.

## Not done

Ideas that were considered and left out to keep the scope close to the brief:

- The schema check and backups described above (built, then dropped).
- Editing and deleting products (products have no unique id yet; barcodes cannot serve as one because the sample data repeats a barcode).
- Search, sorting, filtering and pagination of the table.
- A category dropdown built from existing categories, and suggestions for manufacturers.
- A fixed list of Availability values.
- Export to CSV or JSON.
- Automated tests (PHPUnit).
- Authentication. Anyone who can open the page can add products; the CSRF token only proves the request came from the page's own form.
