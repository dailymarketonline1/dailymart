<?php
// product.php
session_start();
require_once 'config/database.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.full_name as vendor_name,
           u.id as vendor_id, vp.store_name, vp.is_verified
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.vendor_id = u.id
    LEFT JOIN vendor_profiles vp ON u.id = vp.user_id
    WHERE p.slug = ? AND p.is_active = 1
");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC");
$stmt->execute([$product['id']]);
$images = $stmt->fetchAll();

if (empty($images)) {
    $images = [['image_url' => 'https://via.placeholder.com/600x600/eee/333?text=' . urlencode($product['name'])]];
}

$stmt = $pdo->prepare("SELECT * FROM product_attributes WHERE product_id = ?");
$stmt->execute([$product['id']]);
$attributes = $stmt->fetchAll();

// Related products
$stmt = $pdo->prepare("
    SELECT p.*, pi.image_url
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
    LIMIT 8
");
$stmt->execute([$product['category_id'], $product['id']]);
$related_products = $stmt->fetchAll();

// Reviews
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name 
    FROM reviews r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.is_approved = 1
    ORDER BY r.created_at DESC LIMIT 10
");
$stmt->execute([$product['id']]);
$reviews = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT COALESCE(AVG(rating), 0) as avg_rating, COUNT(*) as total_reviews
    FROM reviews WHERE product_id = ? AND is_approved = 1
");
$stmt->execute([$product['id']]);
$rating_data = $stmt->fetch();

$price = $product['discount_price'] ?? $product['price'];
$discount = $product['discount_price'] ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?redirect=product&slug=' . $slug);
        exit;
    }
    $quantity = intval($_POST['quantity'] ?? 1);
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
    $stmt->execute([$user_id, $product['id'], $quantity, $quantity]);
    $_SESSION['cart_message'] = 'Product added to cart!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - DailyMarket</title>
    <meta name="description" content="<?php echo htmlspecialchars(strip_tags($product['short_description'] ?? $product['description'])); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f1f3f6; padding-top: 80px; }
        :root { --daraz-orange: #ff6a00; --daraz-blue: #0f1463; --daraz-gray: #75757a; }
        .product-main { background: #fff; border-radius: 8px; padding: 25px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .gallery-main img { width: 100%; height: 400px; object-fit: contain; cursor: zoom-in; }
        .gallery-thumbs { display: flex; gap: 8px; margin-top: 10px; overflow-x: auto; }
        .gallery-thumbs img { width: 70px; height: 70px; object-fit: cover; border-radius: 4px; border: 2px solid transparent; cursor: pointer; }
        .gallery-thumbs img:hover, .gallery-thumbs img.active { border-color: var(--daraz-orange); }
        .product-title { font-size: 1.4rem; font-weight: 600; color: #333; }
        .product-price { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .product-price .current { font-size: 1.8rem; font-weight: 700; color: var(--daraz-orange); }
        .product-price .original { font-size: 1.1rem; color: var(--daraz-gray); text-decoration: line-through; margin-left: 12px; }
        .product-price .discount { background: #fce4d6; color: var(--daraz-orange); padding: 2px 10px; border-radius: 4px; font-weight: 600; margin-left: 10px; }
        .vendor-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin: 15px 0; }
        .btn-add-cart { background: var(--daraz-orange); color: #fff; border: none; padding: 12px 40px; border-radius: 4px; font-weight: 600; }
        .btn-add-cart:hover { background: #e55d00; color: #fff; }
        .qty-selector { display: flex; align-items: center; border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
        .qty-selector button { background: #f5f5f5; border: none; padding: 8px 16px; font-size: 1.2rem; cursor: pointer; }
        .qty-selector input { width: 50px; text-align: center; border: none; padding: 8px 0; }
        .reviews-section { background: #fff; border-radius: 8px; padding: 20px; margin-top: 20px; }
        .review-item { border-bottom: 1px solid #f0f0f0; padding: 15px 0; }
        .related-product-card { background: #fff; border-radius: 8px; padding: 10px; text-align: center; transition: all 0.3s ease; height: 100%; }
        .related-product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .related-product-card img { width: 100%; height: 150px; object-fit: cover; border-radius: 4px; }
        .related-product-card .name { font-size: 0.9rem; color: #333; margin: 8px 0 4px; overflow: hidden; height: 40px; }
        .related-product-card .price { font-weight: 700; color: var(--daraz-orange); }
        @media (max-width: 768px) { .gallery-main img { height: 250px; } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="container py-3">
        <div class="product-main row g-4">
            <div class="col-md-5">
                <div class="gallery-main">
                    <img src="<?php echo htmlspecialchars($images[0]['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" id="mainImage">
                </div>
                <div class="gallery-thumbs">
                    <?php foreach ($images as $index => $img): ?>
                        <img src="<?php echo htmlspecialchars($img['image_url']); ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" onclick="changeImage(this.src, this)">
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-7">
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                <div class="product-price">
                    <span class="current">Rs. <?php echo number_format($price); ?></span>
                    <?php if ($product['discount_price']): ?>
                        <span class="original">Rs. <?php echo number_format($product['price']); ?></span>
                        <span class="discount">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-meta">
                    <span><i class="fas fa-box"></i> In Stock: <?php echo $product['quantity']; ?></span>
                    <?php if ($product['weight']): ?>
                        <span><i class="fas fa-weight"></i> Weight: <?php echo $product['weight'] . ' ' . $product['weight_unit']; ?></span>
                    <?php endif; ?>
                    <?php if ($product['color']): ?>
                        <span><i class="fas fa-palette"></i> Color: <?php echo htmlspecialchars($product['color']); ?></span>
                    <?php endif; ?>
                    <?php if ($product['brand']): ?>
                        <span><i class="fas fa-tag"></i> Brand: <?php echo htmlspecialchars($product['brand']); ?></span>
                    <?php endif; ?>
                </div>
                <div class="vendor-card">
                    <div class="d-flex justify-content-between">
                        <div>
                            <div class="store-name"><i class="fas fa-store-alt"></i> <?php echo htmlspecialchars($product['store_name'] ?? $product['vendor_name']); ?>
                                <?php if ($product['is_verified']): ?><span class="text-success"><i class="fas fa-check-circle"></i> Verified</span><?php endif; ?>
                            </div>
                            <div class="rating"><i class="fas fa-star text-warning"></i> <?php echo number_format($rating_data['avg_rating'], 1); ?> (<?php echo number_format($rating_data['total_reviews']); ?> reviews)</div>
                        </div>
                    </div>
                </div>
                <form method="POST">
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <div class="qty-selector">
                            <button type="button" onclick="changeQty(-1)">−</button>
                            <input type="number" name="quantity" id="qtyInput" value="1" min="1" max="<?php echo $product['quantity']; ?>">
                            <button type="button" onclick="changeQty(1)">+</button>
                        </div>
                        <button type="submit" name="add_to_cart" class="btn-add-cart"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-lg-8">
                <div class="product-description bg-white p-4 rounded-3">
                    <h5>Product Description</h5>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                </div>
            </div>
            <div class="col-lg-4">
                <?php if (!empty($attributes)): ?>
                    <div class="bg-white p-4 rounded-3">
                        <h5>Product Details</h5>
                        <table class="table table-sm">
                            <?php foreach ($attributes as $attr): ?>
                                <tr><td><?php echo htmlspecialchars($attr['attribute_name']); ?></td><td><?php echo htmlspecialchars($attr['attribute_value']); ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="reviews-section">
            <h5>Customer Reviews</h5>
            <?php if (empty($reviews)): ?>
                <p class="text-muted">No reviews yet.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-item">
                        <span class="fw-bold"><?php echo htmlspecialchars($review['full_name'] ?? 'Anonymous'); ?></span>
                        <span class="text-muted ms-2"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></span>
                        <span class="ms-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?> text-warning" style="font-size:0.8rem;"></i>
                            <?php endfor; ?>
                        </span>
                        <div><?php echo htmlspecialchars($review['comment']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (!empty($related_products)): ?>
            <div class="mt-4">
                <h5>Related Products</h5>
                <div class="row g-3">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-6 col-md-3">
                            <a href="product.php?slug=<?php echo $related['slug']; ?>" class="text-decoration-none">
                                <div class="related-product-card">
                                    <img src="<?php echo htmlspecialchars($related['image_url'] ?? 'https://via.placeholder.com/150'); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                    <div class="name"><?php echo htmlspecialchars(substr($related['name'], 0, 40)); ?></div>
                                    <div class="price">Rs. <?php echo number_format($related['discount_price'] ?? $related['price']); ?></div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'footer.php'; ?>
    <script>
        function changeImage(src, el) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.gallery-thumbs img').forEach(img => img.classList.remove('active'));
            el.classList.add('active');
        }
        function changeQty(delta) {
            const input = document.getElementById('qtyInput');
            let val = parseInt(input.value) + delta;
            if (val < 1) val = 1;
            if (val > parseInt(input.max)) val = parseInt(input.max);
            input.value = val;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
