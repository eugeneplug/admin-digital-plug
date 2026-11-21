<?php
// Run with pdo_sqlite enabled. Uses an isolated temporary database.
declare(strict_types=1);
define('PLUGY_ENTRY', true);
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/admin/assets';
require dirname(__DIR__) . '/app/bootstrap.php';
set_exception_handler(static function (Throwable $error): never { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); });
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA user_version = 1');
$db->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, slug TEXT UNIQUE, excerpt TEXT, body TEXT, status TEXT, published_at INTEGER, updated_at INTEGER)');
$db->exec("INSERT INTO articles (title, slug, body, status) VALUES ('Existing', 'existing', 'Body', 'draft')");
migrateFields($db); migrateFields($db);
check((int) $db->query('SELECT COUNT(*) FROM pages')->fetchColumn() === 1, 'Migration must be idempotent');
check($db->query('SELECT title FROM articles')->fetchColumn() === 'Existing', 'Migration must preserve articles');
$definition = [['key'=>'heading','label'=>'Heading','type'=>'text','required'=>true],['key'=>'items','label'=>'Items','type'=>'repeater','children'=>[['key'=>'title','label'=>'Title','type'=>'text','required'=>true]],'required'=>false]];
saveFieldGroup($db, 0, ['title'=>'Article fields','scope'=>'article','target_id'=>0,'definition'=>json_encode($definition)]);
$group = (int) $db->lastInsertId();
$values = prepareFieldValues($db, 'article', 1, json_encode([$group=>['heading'=>'<script>test</script>','items'=>[['title'=>'First'],['title'=>'Second']]]]));
writeTransaction($db, fn() => storeFieldValues($db, 'article:1', $values));
fieldContext('article:1');
check(count(get_field('items')) === 2, 'Repeater must return rows');
ob_start(); field('heading'); $html = ob_get_clean();
check($html === '&lt;script&gt;test&lt;/script&gt;', 'Output must be escaped');
check(get_field('heading', 'article:2') === null, 'Records must be isolated');
check(get_field('heading', 'options') === null, 'Options must be isolated');
check(get_field('not_found', null, 'fallback') === 'fallback', 'Default value');
try { validateFieldValues(validateDefinitions($definition), ['items'=>[]]); throw new RuntimeException('Required value accepted'); } catch (ValidationException) {}
try { validateDefinitions([$definition[0],$definition[0]]); throw new RuntimeException('Duplicate key accepted'); } catch (ValidationException) {}
try { saveFieldGroup($db,0,['title'=>'Duplicate','scope'=>'article','target_id'=>0,'definition'=>json_encode($definition)]); throw new RuntimeException('Cross-group duplicate accepted'); } catch (ValidationException) {}
check(!fieldUrl('javascript:alert(1)') && fieldUrl('/image.jpg') && fieldUrl('http://example.com/image.jpg'), 'URL validation');
try { saveThemeFile('index.php','<?php function broken(', ''); throw new RuntimeException('Invalid PHP accepted'); } catch (ValidationException) {}
try { readThemeFile('../app/database.php'); throw new RuntimeException('Traversal accepted'); } catch (LogicException) {}
$articleId = writeTransaction($db, fn() => saveArticle($db, 0, ['title'=>'New','slug'=>'new','excerpt'=>'','body'=>'Text','status'=>'published']));
check($articleId === 2, 'Article save must return the ID');
echo "PASS: migration, field validation, repeaters, contexts, escaping, PHP validation, article save\n";
