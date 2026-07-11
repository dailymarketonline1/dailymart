<?php
session_start();
require_once 'config/database.php';

// Get categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name LIMIT 8");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
    $categories = [
        ['slug' => 'electronics', 'name' => 'Electronics', 'icon_class' => 'mobile-alt'],
        ['slug' => 'clothing', 'name' => 'Clothing', 'icon_class' => 'tshirt'],
        ['slug' => 'books', 'name' => 'Books', 'icon_class' => 'book'],
        ['slug' => 'home-kitchen', 'name' => 'Home & Kitchen', 'icon_class' => 'utensils'],
        ['slug' => 'beauty', 'name' => 'Beauty', 'icon_class' => 'spa'],
        ['slug' => 'sports', 'name' => 'Sports', 'icon_class' => 'futbol'],
    ];
}

// Get featured products
$featured_products = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, pi.image_url 
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.is_featured = 1 AND p.is_active = 1
        LIMIT 8
    ");
    $featured_products = $stmt->fetchAll();
} catch (Exception $e) {}

// Get best sellers
$best_sellers = [];
try {
    $stmt = $pdo->query("
        SELECT p.*, pi.image_url 
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.is_active = 1
        ORDER BY p.total_orders DESC
        LIMIT 8
    ");
    $best_sellers = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DailyMarket - Pakistan's Best Online Shopping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #ff6a00;
            --secondary: #0f1463;
            --gray: #75757a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f3f6; padding-top: 80px; }

        .top-bar { background: var(--secondary); color: #fff; padding: 8px 0; font-size: 0.9rem; }
        .top-bar a { color: #fff; text-decoration: none; margin: 0 10px; }
        .top-bar a:hover { color: var(--primary); }

        .main-header { background: #fff; border-bottom: 2px solid var(--primary); position: fixed; top: 0; left: 0; right: 0; z-index: 1000; }
        .main-header .logo h2 { color: var(--primary); font-weight: 700; }
        .main-header .logo h2 span { color: var(--secondary); }

        .search-box { max-width: 500px; margin: 0 auto; }
        .search-box input { border-radius: 25px 0 0 25px; border: 2px solid var(--primary); padding: 10px 20px; }
        .search-box button { border-radius: 0 25px 25px 0; background: var(--primary); color: #fff; border: 2px solid var(--primary); padding: 10px 25px; }
        .search-box button:hover { background: var(--secondary); border-color: var(--secondary); }

        .header-icons { display: flex; align-items: center; gap: 20px; }
        .header-icons a { color: #333; font-size: 1.2rem; position: relative; }
        .header-icons a:hover { color: var(--primary); }
        .header-icons .cart-count { position: absolute; top: -8px; right: -10px; background: var(--primary); color: #fff; border-radius: 50%; width: 20px; height: 20px; font-size: 0.7rem; display: flex; align-items: center; justify-content: center; }

        .navbar { background: var(--secondary); }
        .navbar .nav-link { color: #fff !important; font-weight: 500; padding: 10px 20px; }
        .navbar .nav-link:hover { background: rgba(255,255,255,0.1); border-radius: 5px; }

        .hero-slider { background: linear-gradient(135deg, var(--secondary), #1a237e); border-radius: 12px; padding: 40px 50px; margin-bottom: 25px; color: #fff; position: relative; overflow: hidden; min-height: 320px; }
        .hero-slider h1 { font-size: 2.8rem; font-weight: 700; }
        .hero-slider h1 span { color: var(--primary); }
        .hero-slider .btn-shop { background: var(--primary); color: #fff; padding: 14px 40px; border-radius: 30px; font-weight: 600; text-decoration: none; display: inline-block; }
        .hero-slider .btn-shop:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255, 106, 0, 0.4); color: #fff; }

        .category-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 10px; margin-bottom: 25px; }
        .category-card { background: #fff; border-radius: 8px; padding: 15px 10px; text-align: center; text-decoration: none; color: #333; box-shadow: 0 1px 4px rgba(0,0,0,0.08); transition: all 0.3s ease; }
        .category-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .category-card i { font-size: 2rem; color: var(--primary); }
        .category-card .name { font-size: 0.75rem; margin-top: 6px; display: block; }

        .product-card { background: #fff; border-radius: 8px; padding: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid transparent; height: 100%; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.12); border-color: var(--primary); }
        .product-card img { width: 100%; height: 180px; object-fit: cover; border-radius: 4px; }
        .product-card .name { font-size: 0.85rem; color: #333; margin: 8px 0 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; height: 40px; }
        .product-card .name a { color: #333; text-decoration: none; }
        .product-card .name a:hover { color: var(--primary); }
        .product-card .price { font-weight: 700; color: var(--primary); font-size: 1rem; }
        .product-card .original-price { font-size: 0.8rem; color: var(--gray); text-decoration: line-through; margin-left: 5px; }

        .flash-deals { background: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .flash-deals .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .flash-deals .header h5 { font-weight: 700; color: var(--primary); margin: 0; }
        .flash-deals .timer { display: flex; gap: 8px; }
        .flash-deals .timer .time-box { background: #333; color: #fff; padding: 4px 10px; border-radius: 4px; font-weight: 600; font-size: 0.9rem; }

        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .section-header h5 { font-weight: 700; color: #333; margin: 0; }
        .section-header a { color: var(--primary); text-decoration: none; font-weight: 500; }

        .app-banner { background: linear-gradient(135deg, #0f1463, #1a237e); border-radius: 12px; padding: 30px 40px; color: #fff; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .app-banner .btn-download { background: var(--primary); color: #fff; padding: 10px 30px; border-radius: 30px; text-decoration: none; }

        .footer { background: #2c2b2b; color: #fff; padding: 50px 0 20px; margin-top: 50px; }
        .footer h5 { color: var(--primary); margin-bottom: 20px; }
        .footer ul { list-style: none; padding: 0; }
        .footer ul li { margin-bottom: 10px; }
        .footer ul li a { color: #bbb; text-decoration: none; transition: color 0.3s ease; }
        .footer ul li a:hover { color: var(--primary); }
        .footer .social-icons a { color: #fff; margin-right: 15px; font-size: 1.2rem; }
        .footer .social-icons a:hover { color: var(--primary); }
        .footer .copyright { border-top: 1px solid #444; padding-top: 20px; margin-top: 30px; color: #888; }

        @media (max-width: 768px) {
            .hero-slider { padding: 25px; }
            .hero-slider h1 { font-size: 1.8rem; }
            .category-grid { grid-template-columns: repeat(4, 1fr); }
            .product-card img { height: 140px; }
            .app-banner { flex-direction: column; text-align: center; gap: 15px; }
            .search-box { max-width: 100%; margin: 10px 0; }
        }
        @media (max-width: 480px) {
            .category-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span><i class="fas fa-truck"></i> Free Delivery on orders over PKR 2000</span>
                    <span class="ms-3"><i class="fas fa-phone"></i> +92 300 7015826</span>
                </div>
                <div class="col-md-6 text-end">
                    <a href="register_vendor.php"><i class="fas fa-store"></i> Become a Seller</a>
                    <a href="login.php"><i class="fas fa-user"></i> Login / Register</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="container">
            <div class="row align-items-center py-2">
                <div class="col-md-3 logo">
                    <h2>Daily<span>Market</span></h2>
                </div>
                <div class="col-md-6">
                    <div class="search-box">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search for products...">
                            <button><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="header-icons justify-content-end">
                        <a href="wishlist.php"><i class="fas fa-heart"></i></a>
                        <a href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count">0</span>
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-link text-dark p-0" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle" style="font-size: 1.5rem;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if (isset($_SESSION['user_id'])): ?>
                                    <li><a class="dropdown-item" href="profile.php">My Account</a></li>
                                    <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="login.php">Login</a></li>
                                    <li><a class="dropdown-item" href="register.php">Register</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Categories</a>
                        <ul class="dropdown-menu">
                            <?php foreach ($categories as $cat): ?>
                                <li><a class="dropdown-item" href="category.php?slug=<?php echo $cat['slug']; ?>"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="category.php?slug=all&sort=popular">Best Sellers</a></li>
                    <li class="nav-item"><a class="nav-link" href="category.php?slug=all&sort=newest">New Arrivals</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="register_vendor.php"><i class="fas fa-store"></i> Seller Center</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">

        <!-- Hero Slider -->
        <div class="hero-slider">
            <div>
                <h1>Shop to Get <br><span>What You Love</span></h1>
                <p>Get the best deals from top vendors across Pakistan.</p>
                <a href="category.php?slug=all" class="btn-shop">Shop Now <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <!-- Categories -->
        <div class="category-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="category.php?slug=<?php echo $cat['slug']; ?>" class="category-card">
                    <i class="fas fa-<?php echo $cat['icon_class'] ?? 'tag'; ?>"></i>
                    <span class="name"><?php echo htmlspecialchars($cat['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Flash Deals -->
        <div class="flash-deals">
            <div class="header">
                <h5><i class="fas fa-bolt"></i> Flash Deals</h5>
                <div class="timer">
                    <span class="time-box">02 <span>Hours</span></span>
                    <span class="time-box">45 <span>Mins</span></span>
                    <span class="time-box">30 <span>Secs</span></span>
                </div>
            </div>
            <div class="row g-3">
                <?php 
                $flash_products = !empty($best_sellers) ? array_slice($best_sellers, 0, 6) : [];
                foreach ($flash_products as $product): 
                    $price = $product['discount_price'] ?? $product['price'];
                    $discount = $product['discount_price'] ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
                ?>
                    <div class="col-6 col-md-2">
                        <div class="product-card position-relative">
                            <?php if ($discount > 0): ?>
                                <span class="discount-badge">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                            <a href="product.php?slug=<?php echo $product['slug']; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/200x180/eee/333?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </a>
                            <div class="name">
                                <a href="product.php?slug=<?php echo $product['slug']; ?>"><?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?></a>
                            </div>
                            <div class="price">Rs. <?php echo number_format($price); ?></div>
                            <?php if ($product['discount_price']): ?>
                                <div class="original-price">Rs. <?php echo number_format($product['price']); ?></div>
                            <?php endif; ?>
                            <div style="font-size:0.7rem;color:var(--gray);">🔥 <?php echo number_format($product['total_orders'] ?? 0); ?> sold</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Featured Products -->
        <?php if (!empty($featured_products)): ?>
            <div class="section-header">
                <h5><i class="fas fa-star text-warning"></i> Featured Products</h5>
                <a href="category.php?slug=all">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="row g-3 mb-4">
                <?php foreach ($featured_products as $product): 
                    $price = $product['discount_price'] ?? $product['price'];
                    $discount = $product['discount_price'] ? round((($product['price'] - $product['discount_price']) / $product['price']) * 100) : 0;
                ?>
                    <div class="col-6 col-md-3">
                        <div class="product-card position-relative">
                            <?php if ($discount > 0): ?>
                                <span class="discount-badge">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                            <a href="product.php?slug=<?php echo $product['slug']; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/200x180/eee/333?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </a>
                            <div class="name">
                                <a href="product.php?slug=<?php echo $product['slug']; ?>"><?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?></a>
                            </div>
                            <div class="price">Rs. <?php echo number_format($price); ?></div>
                            <?php if ($product['discount_price']): ?>
                                <div class="original-price">Rs. <?php echo number_format($product['price']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Best Sellers -->
        <?php if (!empty($best_sellers)): ?>
            <div class="section-header">
                <h5><i class="fas fa-trophy text-warning"></i> Best Selling</h5>
                <a href="category.php?slug=all&sort=popular">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="row g-3 mb-4">
                <?php foreach (array_slice($best_sellers, 0, 8) as $product): 
                    $price = $product['discount_price'] ?? $product['price'];
                ?>
                    <div class="col-6 col-md-3">
                        <div class="product-card position-relative">
                            <a href="product.php?slug=<?php echo $product['slug']; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/200x180/eee/333?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </a>
                            <div class="name">
                                <a href="product.php?slug=<?php echo $product['slug']; ?>"><?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?></a>
                            </div>
                            <div class="price">Rs. <?php echo number_format($price); ?></div>
                            <div style="font-size:0.7rem;color:var(--gray);">🔥 <?php echo number_format($product['total_orders'] ?? 0); ?> sold</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- App Download Banner -->
        <div class="app-banner">
            <div>
                <h5><i class="fas fa-mobile-alt me-2"></i> Download the DailyMarket App</h5>
                <p>Get the best deals on the go. Shop anytime, anywhere.</p>
            </div>
            <a href="#" class="btn-download"><i class="fas fa-download"></i> Download Now</a>
        </div>

    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3">
                    <h5>DailyMarket</h5>
                    <p style="color:#bbb;">Your one-stop marketplace for quality products from trusted vendors across Pakistan.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>For Vendors</h5>
                    <ul>
                        <li><a href="register_vendor.php">Sell on DailyMarket</a></li>
                        <li><a href="vendor_login.php">Vendor Dashboard</a></li>
                        <li><a href="#">Vendor FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact Info</h5>
                    <ul>
                        <li><i class="fas fa-phone"></i> +92 300 7015826</li>
                        <li><i class="fas fa-envelope"></i> info@dailymarket.online</li>
                        <li><i class="fas fa-map-marker-alt"></i> Karachi, Pakistan</li>
                    </ul>
                </div>
            </div>
            <div class="copyright text-center">
                <p>&copy; 2025 DailyMarket. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
