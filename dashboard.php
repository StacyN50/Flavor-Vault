<?php
session_start();

/*
=================================================
RENDER POSTGRESQL CONNECTION (IMPORTANT FIX)
=================================================
Render provides DATABASE_URL automatically.
We parse it instead of manual env vars.
=================================================
*/

$dbUrl = getenv("DATABASE_URL");

if ($dbUrl) {
    $db = parse_url($dbUrl);

    $host = $db["host"];
    $port = $db["port"] ?? 5432;
    $user = $db["user"];
    $pass = $db["pass"];
    $dbname = ltrim($db["path"], "/");

} else {
    // fallback (local dev)
    $host = getenv("DB_HOST");
    $dbname = getenv("DB_NAME");
    $user = getenv("DB_USER");
    $pass = getenv("DB_PASS");
    $port = 5432;
}

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $e) {
    die("Database connection failed");
}

/*
=================================================
AUTH
=================================================
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/*
=================================================
DATA
=================================================
*/
$search = trim($_GET['search'] ?? '');
$searchTerm = "%$search%";

$stmt = $pdo->prepare("
    SELECT *
    FROM recipes
    WHERE user_id = :uid
    AND (title ILIKE :search OR category ILIKE :search)
    ORDER BY id DESC
    LIMIT 6
");

$stmt->execute([
    ':uid' => $user_id,
    ':search' => $searchTerm
]);

$recipes = $stmt->fetchAll();

/*
=================================================
STATS
=================================================
*/
$totalRecipes = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE user_id=?");
$totalRecipes->execute([$user_id]);
$totalRecipes = $totalRecipes->fetchColumn();

$totalLikes = $pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn();

$totalFav = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=?");
$totalFav->execute([$user_id]);
$totalFav = $totalFav->fetchColumn();

$totalViews = $pdo->prepare("SELECT COUNT(*) FROM recipe_views WHERE user_id=?");
$totalViews->execute([$user_id]);
$totalViews = $totalViews->fetchColumn();

/*
=================================================
CATEGORY DATA
=================================================
*/
$catStmt = $pdo->prepare("
    SELECT category, COUNT(*) as count
    FROM recipes
    WHERE user_id=?
    GROUP BY category
");
$catStmt->execute([$user_id]);
$categories = $catStmt->fetchAll();

/*
=================================================
USER PROFILE
=================================================
*/
$userStmt = $pdo->prepare("SELECT name,email FROM users WHERE id=?");
$userStmt->execute([$user_id]);
$userProfile = $userStmt->fetch();

?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FlavorVault Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
    margin:0;
    font-family:system-ui;
    background:#0b1220;
    color:#fff;
}

.container{
    max-width:1200px;
    margin:auto;
    padding:20px;
}

.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

input{
    padding:10px;
    border-radius:8px;
    border:none;
}

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
}

.card{
    background:#111b2e;
    padding:15px;
    border-radius:12px;
}

.kpi{
    font-size:22px;
    color:#38bdf8;
    font-weight:bold;
}

.recipe img{
    width:100%;
    height:160px;
    object-fit:cover;
    border-radius:10px;
}

button{
    padding:6px;
    margin-top:5px;
}
</style>
</head>

<body>

<div class="container">

<!-- HEADER -->
<div class="topbar">
    <h2>🍲 FlavorVault Dashboard</h2>

    <form>
        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search recipes...">
    </form>
</div>

<!-- USER -->
<div class="card">
    <strong><?= htmlspecialchars($userProfile['name']) ?></strong><br>
    <small><?= htmlspecialchars($userProfile['email']) ?></small>
</div>

<br>

<!-- KPI -->
<div class="grid">

<div class="card">Recipes <div class="kpi"><?= $totalRecipes ?></div></div>
<div class="card">Likes <div class="kpi"><?= $totalLikes ?></div></div>
<div class="card">Favorites <div class="kpi"><?= $totalFav ?></div></div>
<div class="card">Views <div class="kpi"><?= $totalViews ?></div></div>

</div>

<br>

<!-- CHART -->
<div class="card">
<canvas id="chart"></canvas>
</div>

<script>
new Chart(document.getElementById("chart"), {
    type: "bar",
    data: {
        labels: <?= json_encode(array_column($categories,'category')) ?>,
        datasets: [{
            label: "Recipes",
            data: <?= json_encode(array_column($categories,'count')) ?>
        }]
    }
});
</script>

<br>

<!-- RECIPES -->
<div class="grid">

<?php foreach($recipes as $r): ?>

<?php
$image = !empty($r['image'])
    ? "assets/uploads/".$r['image']
    : "assets/images/default.jpg";
?>

<div class="card recipe">

<img src="<?= $image ?>">

<h3><?= htmlspecialchars($r['title']) ?></h3>
<small><?= htmlspecialchars($r['category']) ?></small>

<br>

<button onclick="like(<?= $r['id'] ?>)">❤️ Like</button>
<button onclick="fav(<?= $r['id'] ?>)">⭐ Fav</button>

</div>

<?php endforeach; ?>

</div>

</div>

<script>
async function like(id){
    await fetch("ajax/like.php?id="+id);
}

async function fav(id){
    await fetch("ajax/fav.php?id="+id);
}
</script>

</body>
</html>
