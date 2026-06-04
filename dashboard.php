<?php
session_start();

/*
=================================================
DATABASE CONNECTION (PDO - POSTGRESQL)
=================================================
*/
$host = getenv("DB_HOST");
$db   = getenv("DB_NAME");
$user = getenv("DB_USER");
$pass = getenv("DB_PASS");

try {
    $pdo = new PDO(
        "pgsql:host=$host;dbname=$db",
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
AUTH CHECK
=================================================
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/*
=================================================
CORE DATA
=================================================
*/
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 6;
$offset = ($page - 1) * $limit;

$searchTerm = "%$search%";

/* Recipes */
$stmt = $pdo->prepare("
    SELECT *
    FROM recipes
    WHERE user_id = :uid
    AND (title ILIKE :search OR category ILIKE :search)
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':search', $searchTerm);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$recipes = $stmt->fetchAll();

/*
=================================================
ANALYTICS (KPIs)
=================================================
*/
$totalRecipes = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE user_id=?");
$totalRecipes->execute([$user_id]);
$totalRecipes = (int)$totalRecipes->fetchColumn();

$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalLikes = (int)$pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn();

$totalFav = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=?");
$totalFav->execute([$user_id]);
$totalFav = (int)$totalFav->fetchColumn();

$totalViews = $pdo->prepare("SELECT COUNT(*) FROM recipe_views WHERE user_id=?");
$totalViews->execute([$user_id]);
$totalViews = (int)$totalViews->fetchColumn();

/*
=================================================
CATEGORY PERFORMANCE
=================================================
*/
$catStmt = $pdo->prepare("
    SELECT category,
           COUNT(*) AS count
    FROM recipes
    WHERE user_id=?
    GROUP BY category
    ORDER BY count DESC
");
$catStmt->execute([$user_id]);
$categories = $catStmt->fetchAll();

/*
=================================================
RECENT ACTIVITY STREAM
=================================================
*/
$actStmt = $pdo->prepare("
    SELECT activity, created_at
    FROM activity_log
    WHERE user_id=?
    ORDER BY created_at DESC
    LIMIT 6
");
$actStmt->execute([$user_id]);
$activities = $actStmt->fetchAll();

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
<title>FlavorVault Analytics Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root{
    --bg:#0b1220;
    --panel:#111b2e;
    --panel2:#16233a;
    --text:#e5e7eb;
    --muted:#94a3b8;
    --accent:#38bdf8;
    --good:#22c55e;
    --warn:#f59e0b;
}

body{
    margin:0;
    font-family:system-ui;
    background:var(--bg);
    color:var(--text);
}

.layout{
    display:grid;
    grid-template-columns:260px 1fr;
    min-height:100vh;
}

/* SIDEBAR */
.sidebar{
    background:var(--panel);
    padding:20px;
}

.brand{
    font-size:18px;
    font-weight:700;
    margin-bottom:20px;
}

.profile{
    background:var(--panel2);
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
}

.nav{
    display:flex;
    flex-direction:column;
    gap:10px;
}

.nav div{
    padding:10px;
    border-radius:8px;
    background:transparent;
    color:var(--muted);
}

/* MAIN */
.main{
    padding:20px;
}

/* TOP BAR */
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
    width:260px;
}

/* KPI CARDS */
.kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:12px;
    margin-bottom:20px;
}

.card{
    background:var(--panel);
    padding:15px;
    border-radius:12px;
}

.kpi-value{
    font-size:22px;
    font-weight:700;
    color:var(--accent);
}

/* GRID */
.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
    gap:15px;
}

/* RECIPES */
.recipe img{
    width:100%;
    height:180px;
    object-fit:cover;
    border-radius:10px;
}

/* ACTIVITY */
.activity{
    font-size:14px;
    color:var(--muted);
    border-left:2px solid var(--accent);
    padding-left:10px;
    margin:8px 0;
}

.badge{
    font-size:12px;
    color:var(--good);
}

canvas{
    background:var(--panel);
    border-radius:12px;
    padding:10px;
}
</style>
</head>

<body>

<div class="layout">

<!-- SIDEBAR -->
<div class="sidebar">

    <div class="brand">🍲 FlavorVault</div>

    <div class="profile">
        <strong><?= htmlspecialchars($userProfile['name']) ?></strong><br>
        <small><?= htmlspecialchars($userProfile['email']) ?></small>
    </div>

    <div class="nav">
        <div>📊 Overview</div>
        <div>📚 Recipes</div>
        <div>⭐ Favorites</div>
        <div>📈 Analytics</div>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <h2>Analytics Dashboard</h2>

        <form>
            <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search recipes...">
        </form>
    </div>

    <!-- KPI SECTION -->
    <div class="kpis">

        <div class="card">
            <div>Recipes</div>
            <div class="kpi-value"><?= $totalRecipes ?></div>
        </div>

        <div class="card">
            <div>Favorites</div>
            <div class="kpi-value"><?= $totalFav ?></div>
        </div>

        <div class="card">
            <div>Total Likes</div>
            <div class="kpi-value"><?= $totalLikes ?></div>
        </div>

        <div class="card">
            <div>Views</div>
            <div class="kpi-value"><?= $totalViews ?></div>
        </div>

    </div>

    <!-- ANALYTICS CHART -->
    <div class="card">
        <h3>Category Performance</h3>
        <canvas id="chart"></canvas>
    </div>

    <script>
    new Chart(document.getElementById("chart"), {
        type: "bar",
        data: {
            labels: <?= json_encode(array_column($categories,'category')) ?>,
            datasets: [{
                label: "Recipes by Category",
                data: <?= json_encode(array_column($categories,'count')) ?>
            }]
        }
    });
    </script>

    <!-- CONTENT GRID -->
    <div class="grid">

        <!-- RECIPES -->
        <div class="card">
            <h3>Recent Recipes</h3>

            <?php foreach($recipes as $r): ?>
                <?php
                $image = !empty($r['image'])
                    ? "assets/uploads/".$r['image']
                    : "assets/images/default.jpg";
                ?>

                <div class="card recipe">
                    <img src="<?= $image ?>" loading="lazy">
                    <h4><?= htmlspecialchars($r['title']) ?></h4>
                    <small class="badge"><?= htmlspecialchars($r['category']) ?></small>

                    <div>
                        <button onclick="like(<?= $r['id'] ?>)">❤️</button>
                        <button onclick="fav(<?= $r['id'] ?>)">⭐</button>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <!-- ACTIVITY STREAM -->
        <div class="card">
            <h3>Activity Stream</h3>

            <?php foreach($activities as $a): ?>
                <div class="activity">
                    <?= htmlspecialchars($a['activity']) ?><br>
                    <small><?= $a['created_at'] ?></small>
                </div>
            <?php endforeach; ?>

        </div>

    </div>

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
