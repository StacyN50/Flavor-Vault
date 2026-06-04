<?php
include("config/db.php");
include("includes/header.php");

$search = $_GET['search'] ?? "";

if ($search !== "") {

    $sql = "SELECT * FROM recipes WHERE title ILIKE :search ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ":search" => "%$search%"
    ]);

} else {

    $sql = "SELECT * FROM recipes ORDER BY id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
}

$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* ===== HERO SECTION ===== */
.hero {
    background: linear-gradient(135deg, #ff7e5f, #feb47b);
    padding: 60px 20px;
    text-align: center;
    color: white;
}

.hero h1 {
    font-size: 40px;
    margin-bottom: 20px;
    font-weight: 700;
}

/* SEARCH BAR */
.hero form {
    display: flex;
    justify-content: center;
    gap: 10px;
    max-width: 500px;
    margin: 0 auto;
}

.hero input {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    outline: none;
}

.hero button {
    padding: 12px 20px;
    border: none;
    background: #222;
    color: white;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
}

.hero button:hover {
    background: #000;
}

/* GRID */
.recipes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    padding: 40px;
    background: #f8f9fb;
}

/* CARD */
.recipe-card {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    transition: 0.3s;
}

.recipe-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
}

.recipe-card img {
    width: 100%;
    height: 180px;
    object-fit: cover;
}

/* CONTENT */
.recipe-content {
    padding: 15px;
}

.recipe-content h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.recipe-content p {
    color: #777;
    font-size: 14px;
    margin: 5px 0 15px;
}

/* BUTTON */
.recipe-content a {
    display: inline-block;
    padding: 8px 12px;
    background: #ff7e5f;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    font-size: 14px;
    transition: 0.3s;
}

.recipe-content a:hover {
    background: #e95c3d;
}
</style>

<div class="hero">
    <h1>🍽 Discover Amazing Recipes</h1>

    <form method="GET">
        <input type="text" name="search" placeholder="Search recipes...">
        <button>Search</button>
    </form>
</div>

<div class="recipes-grid">

<?php foreach ($recipes as $row): ?>

    <div class="recipe-card">

        <img src="assets/uploads/<?php echo htmlspecialchars($row['image']); ?>">

        <div class="recipe-content">
            <h3><?php echo htmlspecialchars($row['title']); ?></h3>
            <p><?php echo htmlspecialchars($row['category']); ?></p>

            <a href="recipe.php?id=<?php echo $row['id']; ?>">
                View Recipe
            </a>
        </div>

    </div>

<?php endforeach; ?>

</div>

<?php include("includes/footer.php"); ?>
