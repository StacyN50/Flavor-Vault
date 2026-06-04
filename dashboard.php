<?php
session_start();

/*
=================================================
POSTGRESQL CONNECTION (RENDER READY - PDO)
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
SEARCH + PAGINATION
=================================================
*/
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 6;
$offset = ($page - 1) * $limit;

$searchTerm = "%$search%";

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
STATS
=================================================
*/

// recipes
$totalRecipes = $pdo->prepare("SELECT COUNT(*) FROM recipes WHERE user_id=?");
$totalRecipes->execute([$user_id]);
$totalRecipes = $totalRecipes->fetchColumn();

// users
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// likes
$totalLikes = $pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn();

// favorites
$totalFav = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id=?");
$totalFav->execute([$user_id]);
$totalFav = $totalFav->fetchColumn();

// views
$totalViews = $pdo->prepare("SELECT COUNT(*) FROM recipe_views WHERE user_id=?");
$totalViews->execute([$user_id]);
$totalViews = $totalViews->fetchColumn();

/*
=================================================
CATEGORY ANALYTICS
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
RECENT ACTIVITY
=================================================
*/
$actStmt = $pdo->prepare("
    SELECT activity, created_at
    FROM activity_log
    WHERE user_id=?
    ORDER BY created_at DESC
    LIMIT 5
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

<title>FlavorVault Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { font-family: Arial; margin:0; background:#0f172a; color:#fff; }
header { padding:15px; background:#111827; display:flex; justify-content:space-between; align-items:center; }
input { padding:10px; border-radius:8px; border:none; }

.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:15px; padding:15px; }

.card { background:#1e293b; padding:15px; border-radius:10px; }

.recipe img { width:100%; height:180px; object-fit:cover; border-radius:10px; }

.light { background:#f8fafc; color:#111; }

button { cursor:pointer; padding:8px; margin:5px; }

</style>
</head>

<body>

<header>
    <h2>🍲 FlavorVault</h2>

    <form>
        <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search">
    </form>

    <button onclick="toggleTheme()">🌙</button>
</header>

<!-- PROFILE -->
<div class="card">
    <h3>User</h3>
    <p><?= htmlspecialchars($userProfile['name']) ?></p>
    <small><?= htmlspecialchars($userProfile['email']) ?></small>
</div>

<!-- STATS -->
<div class="grid">

<div class="card">Recipes: <?= $totalRecipes ?></div>
<div class="card">Users: <?= $totalUsers ?></div>
<div class="card">Likes: <?= $totalLikes ?></div>
<div class="card">Favorites: <?= $totalFav ?></div>
<div class="card">Views: <?= $totalViews ?></div>

</div>

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

<!-- RECIPES -->
<div class="grid">

<?php foreach($recipes as $r): ?>

<?php
$image = !empty($r['image'])
    ? "assets/uploads/".$r['image']
    : "assets/images/default.jpg";
?>

<div class="card recipe">

<img loading="lazy" src="<?= $image ?>">

<h3><?= htmlspecialchars($r['title']) ?></h3>
<p><?= htmlspecialchars($r['category']) ?></p>

<button onclick="like(<?= $r['id'] ?>)">❤️ Like</button>
<button onclick="fav(<?= $r['id'] ?>)">⭐ Fav</button>

</div>

<?php endforeach; ?>

</div>

<!-- ACTIVITY -->
<div class="card">
<h3>Recent Activity</h3>

<?php foreach($activities as $a): ?>
<p><?= htmlspecialchars($a['activity']) ?></p>
<?php endforeach; ?>

</div>

<script>

function toggleTheme(){
    document.body.classList.toggle("light");
}

async function like(id){
    await fetch("ajax/like.php?id="+id);
}

async function fav(id){
    await fetch("ajax/fav.php?id="+id);
}

</script>

</body>
</html>
