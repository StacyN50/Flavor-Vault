<?php
session_start();
require_once __DIR__ . "/config/db.php";

/*
=================================================
ERROR HANDLING
=================================================
*/
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($conn)) {
    die("Database connection failed.");
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
SEARCH
=================================================
*/
$search = trim($_GET['search'] ?? '');
$searchTerm = "%{$search}%";

$stmt = $conn->prepare("
    SELECT *
    FROM recipes
    WHERE user_id = ?
    AND (
        title LIKE ?
        OR category LIKE ?
    )
    ORDER BY id DESC
");

$stmt->bind_param(
    "is",
    $user_id,
    $searchTerm
);

$stmt->execute();
$recipes = $stmt->get_result();

/*
=================================================
STATS
=================================================
*/

$stmt = $conn->prepare("
    SELECT COUNT(*) total
    FROM recipes
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$totalRecipes = $stmt->get_result()->fetch_assoc()['total'];

$totalUsers = 0;
$result = $conn->query("
    SELECT COUNT(*) total
    FROM users
");
if ($result) {
    $totalUsers = $result->fetch_assoc()['total'];
}

$totalLikes = 0;

try {
    $result = $conn->query("
        SELECT COUNT(*) total
        FROM likes
    ");

    if ($result) {
        $totalLikes = $result->fetch_assoc()['total'];
    }
} catch (Exception $e) {
    $totalLikes = 0;
}

/*
=================================================
CATEGORY ANALYTICS
=================================================
*/

$stmt = $conn->prepare("
    SELECT category, COUNT(*) count
    FROM recipes
    WHERE user_id = ?
    GROUP BY category
    ORDER BY count DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$categories = $stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">

<title>FlavorVault Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>

:root{
    --primary:#ff7a18;
    --secondary:#ffb347;
    --dark:#0f172a;
    --card:#1e293b;
    --border:#334155;
    --white:#fff;
    --success:#10b981;
    --danger:#ef4444;
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:Poppins,sans-serif;
    background:var(--dark);
    color:white;
}

/* HEADER */

.header{
    padding:20px;
    background:#111827;
    border-bottom:1px solid var(--border);
}

.nav{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    flex-wrap:wrap;
}

.logo{
    font-size:24px;
    font-weight:700;
    color:var(--primary);
}

.search-box{
    display:flex;
}

.search-box input{
    padding:12px;
    border:none;
    border-radius:10px;
    width:300px;
    max-width:100%;
}

/* STATS */

.stats{
    padding:25px;
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}

.stat{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:15px;
    padding:20px;
    text-align:center;
    transition:.3s;
}

.stat:hover{
    transform:translateY(-4px);
}

.stat h2{
    color:var(--primary);
    margin-bottom:8px;
}

/* CATEGORY */

.analytics{
    padding:0 25px 25px;
}

.analytics-card{
    background:var(--card);
    border:1px solid var(--border);
    padding:20px;
    border-radius:15px;
}

.analytics h3{
    margin-bottom:15px;
    color:var(--primary);
}

.category{
    display:flex;
    justify-content:space-between;
    padding:10px 0;
    border-bottom:1px solid rgba(255,255,255,.05);
}

/* RECIPES */

.grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
    gap:25px;
    padding:25px;
}

.recipe{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    overflow:hidden;
    transition:.3s;
}

.recipe:hover{
    transform:translateY(-5px);
}

.recipe img{
    width:100%;
    height:220px;
    object-fit:cover;
}

.content{
    padding:20px;
}

.title{
    color:var(--primary);
    font-size:20px;
    font-weight:600;
}

.category-badge{
    display:inline-block;
    margin-top:10px;
    background:rgba(255,122,24,.15);
    color:var(--primary);
    padding:5px 12px;
    border-radius:30px;
    font-size:13px;
}

/* BUTTONS */

.actions{
    margin-top:20px;
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}

.btn{
    text-decoration:none;
    padding:10px 15px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:500;
}

.view{
    background:var(--primary);
    color:white;
}

.edit{
    background:var(--success);
    color:white;
}

.delete{
    background:var(--danger);
    color:white;
}

.like{
    background:white;
    color:black;
}

/* EMPTY */

.empty{
    text-align:center;
    padding:50px;
    color:#94a3b8;
}

@media(max-width:768px){

    .nav{
        flex-direction:column;
    }

    .search-box input{
        width:100%;
    }
}

</style>
</head>
<body>

<header class="header">
    <div class="nav">

        <div class="logo">🍲 FlavorVault</div>

        <form method="GET" class="search-box">
            <input
                type="text"
                name="search"
                placeholder="Search recipes..."
                value="<?= htmlspecialchars($search) ?>"
            >
        </form>

    </div>
</header>

<section class="stats">

    <div class="stat">
        <h2><?= $totalRecipes ?></h2>
        <p>My Recipes</p>
    </div>

    <div class="stat">
        <h2><?= $totalUsers ?></h2>
        <p>Total Users</p>
    </div>

    <div class="stat">
        <h2><?= $totalLikes ?></h2>
        <p>Total Likes</p>
    </div>

</section>

<section class="analytics">

    <div class="analytics-card">

        <h3>📊 Category Analytics</h3>

        <?php while($cat = $categories->fetch_assoc()): ?>

            <div class="category">
                <span><?= htmlspecialchars($cat['category']) ?></span>
                <strong><?= $cat['count'] ?></strong>
            </div>

        <?php endwhile; ?>

    </div>

</section>

<section class="grid">

<?php if($recipes->num_rows > 0): ?>

<?php while($row = $recipes->fetch_assoc()): ?>

<?php

$image = !empty($row['image'])
    ? "assets/uploads/" . htmlspecialchars($row['image'])
    : "assets/images/default-food.jpg";

?>

<div class="recipe" id="recipe-<?= $row['id'] ?>">

    <img
        src="<?= $image ?>"
        alt="<?= htmlspecialchars($row['title']) ?>"
        loading="lazy"
    >

    <div class="content">

        <div class="title">
            <?= htmlspecialchars($row['title']) ?>
        </div>

        <div class="category-badge">
            <?= htmlspecialchars($row['category']) ?>
        </div>

        <div class="actions">

            <a class="btn view"
               href="recipe.php?id=<?= $row['id'] ?>">
               View
            </a>

            <a class="btn edit"
               href="edit_recipe.php?id=<?= $row['id'] ?>">
               Edit
            </a>

            <button
                class="btn delete"
                onclick="deleteRecipe(<?= $row['id'] ?>)">
                Delete
            </button>

            <button
                class="btn like"
                onclick="likeRecipe(<?= $row['id'] ?>)">
                ❤️ Like
            </button>

        </div>

    </div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="empty">
    <h2>No recipes found</h2>
    <p>Start creating your first recipe.</p>
</div>

<?php endif; ?>

</section>

<script>

async function deleteRecipe(id){

    if(!confirm("Delete this recipe?")) return;

    try{

        const response = await fetch(
            `ajax/delete_recipe.php?id=${id}`
        );

        const result = await response.text();

        if(result.trim() === "deleted"){

            const card =
                document.getElementById(`recipe-${id}`);

            if(card){
                card.remove();
            }
        }
        else{
            alert(result);
        }

    }catch(error){

        alert("Delete failed.");

    }
}

async function likeRecipe(id){

    try{

        const response = await fetch(
            `ajax/like_recipe.php?id=${id}`
        );

        const result = await response.text();

        alert(result);

    }catch(error){

        alert("Unable to like recipe.");

    }
}

</script>

</body>
</html>
