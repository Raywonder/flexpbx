<?php
/**
 * FlexPBX Help API
 * Provides help articles, tooltips, and search functionality
 */

header('Content-Type: application/json');

// Load configuration
$config = require_once(__DIR__ . '/config.php');

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get action
$action = $_GET['action'] ?? 'get_context';

// Route actions
switch ($action) {
    case 'get_context':
        getContextHelp($pdo);
        break;

    case 'get_article':
        getArticle($pdo);
        break;

    case 'get_tooltips':
        getTooltips($pdo);
        break;

    case 'search':
        searchHelp($pdo);
        break;

    case 'get_categories':
        getCategories($pdo);
        break;

    case 'get_by_category':
        getByCategory($pdo);
        break;

    case 'track_view':
        trackView($pdo);
        break;

    case 'get_all':
        getAllArticles($pdo);
        break;

    case 'save_article':
        saveArticle($pdo);
        break;

    case 'delete_article':
        deleteArticle($pdo);
        break;

    case 'save_tooltip':
        saveTooltip($pdo);
        break;

    case 'delete_tooltip':
        deleteTooltip($pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Get help articles for current page context
 */
function getContextHelp($pdo) {
    $context = $_GET['context'] ?? 'dashboard';

    try {
        $stmt = $pdo->prepare("
            SELECT article_key, title, content, category, keywords
            FROM help_articles
            WHERE page_context = ? AND is_published = 1
            ORDER BY sort_order ASC, title ASC
        ");
        $stmt->execute([$context]);
        $articles = $stmt->fetchAll();

        // Get related videos (placeholder - can be stored in separate table)
        $videos = [];

        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'videos' => $videos,
            'context' => $context
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch help articles']);
    }
}

/**
 * Get single article
 */
function getArticle($pdo) {
    $articleKey = $_GET['key'] ?? '';

    if (empty($articleKey)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Article key required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM help_articles
            WHERE article_key = ? AND is_published = 1
        ");
        $stmt->execute([$articleKey]);
        $article = $stmt->fetch();

        if ($article) {
            // Get related articles
            $stmt = $pdo->prepare("
                SELECT article_key, title
                FROM help_articles
                WHERE category = ? AND article_key != ? AND is_published = 1
                ORDER BY sort_order ASC
                LIMIT 5
            ");
            $stmt->execute([$article['category'], $articleKey]);
            $related = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'article' => $article,
                'related' => $related
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Article not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch article']);
    }
}

/**
 * Get tooltips for current page
 */
function getTooltips($pdo) {
    $page = $_GET['page'] ?? 'dashboard';

    try {
        $stmt = $pdo->prepare("
            SELECT element_id, title, content, position
            FROM help_tooltips
            WHERE page = ? AND is_active = 1
        ");
        $stmt->execute([$page]);
        $tooltips = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'tooltips' => $tooltips,
            'page' => $page
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch tooltips']);
    }
}

/**
 * Search help articles
 */
function searchHelp($pdo) {
    $query = $_GET['q'] ?? '';

    if (strlen($query) < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Query too short']);
        return;
    }

    try {
        $searchTerm = "%{$query}%";

        $stmt = $pdo->prepare("
            SELECT article_key, title, content, category
            FROM help_articles
            WHERE is_published = 1
            AND (
                title LIKE ? OR
                content LIKE ? OR
                keywords LIKE ?
            )
            ORDER BY
                CASE
                    WHEN title LIKE ? THEN 1
                    WHEN keywords LIKE ? THEN 2
                    ELSE 3
                END,
                title ASC
            LIMIT 20
        ");

        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $articles = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'query' => $query,
            'count' => count($articles)
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Search failed']);
    }
}

/**
 * Get all categories
 */
function getCategories($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT category, COUNT(*) as count
            FROM help_articles
            WHERE is_published = 1
            GROUP BY category
            ORDER BY category ASC
        ");
        $categories = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch categories']);
    }
}

/**
 * Get articles by category
 */
function getByCategory($pdo) {
    $category = $_GET['category'] ?? '';

    if (empty($category)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Category required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT article_key, title, content, page_context
            FROM help_articles
            WHERE category = ? AND is_published = 1
            ORDER BY sort_order ASC, title ASC
        ");
        $stmt->execute([$category]);
        $articles = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'category' => $category
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch articles']);
    }
}

/**
 * Track article view (analytics)
 */
function trackView($pdo) {
    $articleKey = $_POST['article_key'] ?? '';

    if (empty($articleKey)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Article key required']);
        return;
    }

    // Create views table if not exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS help_article_views (
                id INT PRIMARY KEY AUTO_INCREMENT,
                article_key VARCHAR(100),
                viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                user_ip VARCHAR(45),
                INDEX idx_article (article_key),
                INDEX idx_date (viewed_at)
            )
        ");

        $stmt = $pdo->prepare("
            INSERT INTO help_article_views (article_key, user_ip)
            VALUES (?, ?)
        ");
        $stmt->execute([$articleKey, $_SERVER['REMOTE_ADDR'] ?? '']);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to track view']);
    }
}

/**
 * Get all articles (admin)
 */
function getAllArticles($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT *
            FROM help_articles
            ORDER BY category ASC, sort_order ASC, title ASC
        ");
        $articles = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'articles' => $articles
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to fetch articles']);
    }
}

/**
 * Save article (admin)
 */
function saveArticle($pdo) {
    // Check if admin (add proper auth check)

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }

    try {
        if (isset($data['id']) && $data['id']) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE help_articles
                SET title = ?, content = ?, category = ?, page_context = ?,
                    keywords = ?, sort_order = ?, is_published = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['title'],
                $data['content'],
                $data['category'],
                $data['page_context'],
                $data['keywords'] ?? '',
                $data['sort_order'] ?? 0,
                $data['is_published'] ?? 1,
                $data['id']
            ]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO help_articles (article_key, title, content, category, page_context, keywords, sort_order, is_published)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['article_key'],
                $data['title'],
                $data['content'],
                $data['category'],
                $data['page_context'],
                $data['keywords'] ?? '',
                $data['sort_order'] ?? 0,
                $data['is_published'] ?? 1
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save article: ' . $e->getMessage()]);
    }
}

/**
 * Delete article (admin)
 */
function deleteArticle($pdo) {
    $id = $_POST['id'] ?? '';

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Article ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM help_articles WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete article']);
    }
}

/**
 * Save tooltip (admin)
 */
function saveTooltip($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }

    try {
        if (isset($data['id']) && $data['id']) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE help_tooltips
                SET title = ?, content = ?, page = ?, position = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['title'],
                $data['content'],
                $data['page'],
                $data['position'] ?? 'top',
                $data['is_active'] ?? 1,
                $data['id']
            ]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO help_tooltips (element_id, title, content, page, position, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['element_id'],
                $data['title'],
                $data['content'],
                $data['page'],
                $data['position'] ?? 'top',
                $data['is_active'] ?? 1
            ]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save tooltip: ' . $e->getMessage()]);
    }
}

/**
 * Delete tooltip (admin)
 */
function deleteTooltip($pdo) {
    $id = $_POST['id'] ?? '';

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tooltip ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM help_tooltips WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to delete tooltip']);
    }
}
