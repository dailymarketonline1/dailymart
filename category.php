<?php
// category.php
session_start();
require_once 'config/database.php';

$slug = $_GET['slug'] ?? 'all';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;
$sort = $_GET['sort'] ?? 'newest';

$category = null;
if ($slug !== 'all') {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    $category = $stmt->fetch();
}

$sql = "SELECT p.*, pi.image_url, u.full_name as vendor_name 
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        LEFT JOIN users u ON p.vendor_id = u.id
        WHERE p.is_active = 1";
$params = [];

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category['id'];
}

if (!empty($_GET['search'])) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchTerm = '%' . $_GET['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

switch ($sort) {
    case 'price_low': $sql .= " ORDER BY COALESCE(p.discount_price, p.price) ASC"; break;
    case 'price_high': $sql .= " ORDER BY COALESCE(p.discount_price, p.price) DESC"; break;
    case 'popular': $sql .= " ORDER BY p.total_orders DESC"; break;
    default: $sql .= " ORDER BY p.created_at DESC";
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$countSql = "SELECT COUNT(*) FROM products p WHERE p.is_active = 1";
$countParams = [];
if ($category) {
    $countSql .= " AND p.category_id = ?";
    $countParams[] = $category['id'];
}
if (!empty($_GET['search'])) {
    $countSql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$total_products = $stmt->fetchColumn();
$total_pages = ceil($total_products / $per_page);

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $category ? htmlspecialchars($category['name']) : 'All Products'; ?> - DailyMarket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f1f3f6; padding-top: 80px; }
        :root { --daraz-orange: #ff6a00; --daraz-blue: #0f1463; --daraz-gray: #75757a; }
        .category-header { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .filter-sidebar { background: #fff; border-radius: 8px; padding: 20px; position: sticky; top: 100px; }
        .filter-sidebar .category-item { display: block; padding: 5px 0; color: #555; text-decoration: none; }
        .filter-sidebar .category-item:hover, .filter-sidebar .category-item.active { color: var(--daraz-orange); }
        .product-grid { background: #fff; border-radius: 8px; padding: 20px; }
        .product-card { background: #fff; border-radius: 8px; padding: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid transparent; height: 100%; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-color: var(--daraz-orange); }
        .product-card img { width: 100%; height: 180px; object-fit: cover; border-radius: 4px; }
        .product-card .name { font-size: 0.9rem; color: #333; margin: 8px 0 4px; overflow: hidden; height: 40px; }
        .product-card .name a { color: #333; text-decoration: none; }
        .product-card .name a:hover { color: var(--daraz-orange); }
        .product-card .price { font-weight: 700; color: var(--daraz-orange); font-size: 1rem; }
        .product-card .original-price { font-size: 0.8rem; color: var(--daraz-gray); text-decoration: line-through; margin-left: 5px; }
        .product-card .discount-badge { position: absolute; top: 8px; right: 8px; background: #fce4d6; color: var(--daraz-orange); padding: 2px 10px; border-radius: 4px; font-size: 0.7rem; font-weight: 600; }
        .pagination-custom { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .pagination-custom a, .pagination-custom span { padding: 8px 14px; border: 1px solid #ddd; border-radius: 4px; color: #333; text-decoration: none; }
        .pagination-custom a:hover { background: var(--daraz-orange); color: #fff; border-color: var(--daraz-orange); }
        .pagination-custom .active { background: var(--daraz-orange); color: #fff; border-color: var(--daraz-orange); }
        @media (max-width: 768px) { .filter-sidebar { position: static; margin-bottom: 15px; } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container py-3">
        <div class="category-header">
            <div class="d-flex justify-content-between">
                <h4><?php echo $category ? htmlspecialchars($category['name']) : 'All Products'; ?></h4>
                <span class="text-muted"><?php echo number_format($total_products); ?> Products</span>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-lg-3">
                <div class="filter-sidebar">
                    <h6>Categories</h6>
                    <a href="category.php?slug=all" class="category-item <?php echo !$category ? 'active' : ''; ?>">All Categories</a>
                    <?php foreach ($categories as $cat): ?>
                        <a href="category.php?slug=<?php echo $cat['slug']; ?>" class="category-item <?php echo ($category && $category['id'] == $cat['id']) ? 'active' : ''; ?>">
                            <i class="fas fa-angle-right"></i> <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="product-grid">
                    <div class="d-flex justify-content-between mb-3">
                        <span><?php echo number_format($total_products); ?> products found</span>
                        <select class="form-select form-select-sm w-auto" onchange="window.location.href='?slug=<?php echo $slug; ?>&sort='+this.value">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Popular</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    <?php if (empty($products)): ?>
                        <div class="text-center py-5"><i class="fas fa-box-open" style="font-size:3rem;color:#ddd;"></i><h5>No products found</h5></div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($products as $product): 
                                $price = $product['discount_price'] ?? $product['price'];
                                $discount = $product['discount_price'] ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
                            ?>
                                <div class="col-6 col-md-4 col-lg-3">
                                    <div class="product-card position-relative">
                                        <?php if ($discount > 0): ?>
                                            <span class="discount-badge">-<?php echo $discount; ?>%</span>
                                        <?php endif; ?>
                                        <a href="product.php?slug=<?php echo $product['slug']; ?>">
                                            <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/200x180/eee/333?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </a>
                                        <div class="name"><a href="product.php?slug=<?php echo $product['slug']; ?>"><?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?></a></div>
                                        <div class="price">Rs. <?php echo number_format($price); ?></div>
                                        <?php if ($product['discount_price']): ?>
                                            <div class="original-price">Rs. <?php echo number_format($product['price']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-custom">
                                <?php if ($page > 1): ?>
                                    <a href="?slug=<?php echo $slug; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>"><i class="fas fa-chevron-left"></i></a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="active"><?php echo $i; ?></span>
                                    <?php elseif ($i <= 2 || $i > $total_pages - 2 || abs($i - $page) <= 1): ?>
                                        <a href="?slug=<?php echo $slug; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort; ?>"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?slug=<?php echo $slug; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>"><i class="fas fa-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
