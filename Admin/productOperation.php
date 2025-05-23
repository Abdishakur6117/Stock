<?php
session_start();
header('Content-Type: application/json');
require_once '../Connection/connection.php';

$action = $_GET['action'] ?? '';

try {
    $db = new DatabaseConnection();
    $conn = $db->getConnection();
    
    switch ($action) { 
        case 'get_category':
            get_category($conn);
            break;
        case 'display_product':
            display_product($conn);
            break;
            
        case 'create_product':
            create_product($conn);
            break;
            
        case 'update_product':
            update_product($conn);
            break;
            
        case 'delete_product':
            delete_product($conn);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function get_category($conn) {

    if (empty($_SESSION['user_id'])) {
        throw new Exception('User is not logged in');
    }

    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT category_id, name 
        FROM categories 
        WHERE user_id = ?
        ORDER BY name
    ");
    $stmt->execute([$user_id]);

    echo json_encode([
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}


function display_product($conn) {
    if (empty($_SESSION['user_id'])) {
        throw new Exception('User is not logged in');
    }

    $user_id = $_SESSION['user_id'];

    $query = "
        SELECT 
            p.product_id,
            p.name AS product_name,
            p.description,
            c.category_id,
            c.name AS category_name,
            p.price,
            p.quantity_in_stock,
            p.created_at
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([$user_id]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}


function create_product($conn) {

    $required = ['product_name', 'description', 'category_id', 'price', 'quantity'];
    $data = [];

    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
        $data[$field] = trim($_POST[$field]);
    }

    // Hubi in user login yahay
    if (empty($_SESSION['user_id'])) {
        throw new Exception('User is not logged in');
    }
    $user_id = $_SESSION['user_id'];

    // Insert record with user_id
    $stmt = $conn->prepare("
        INSERT INTO products 
        (name, description, category_id, price, quantity_in_stock, user_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $success = $stmt->execute([
        $data['product_name'],
        $data['description'],
        $data['category_id'],
        $data['price'],
        $data['quantity'],
        $user_id
    ]);

    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Product recorded successfully'
        ]);
    } else {
        throw new Exception('Failed to record product');
    }
}
function update_product($conn) {
    // Accept both 'edit_id' and 'id' as the identifier
    $id = $_POST['edit_id'] ?? $_POST['id'] ?? null;
    
    $required = [
        'id' => $id,
        'product_name' => $_POST['edit_product_name'] ?? null,
        'description' => $_POST['edit_description'] ?? null,
        'category_id' => $_POST['edit_category_id'] ?? null,
        'price' => $_POST['edit_price'] ?? null,
        'quantity' => $_POST['edit_quantity'] ?? null
    ];
    
    // Validate required fields
    foreach ($required as $field => $value) {
        if (empty($value)) {
            throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    // Update record
    $stmt = $conn->prepare("
        UPDATE products SET
            name = ?,
            description = ?,
            category_id = ?,
            price = ?,
            quantity_in_stock = ?
        WHERE product_id = ?
    ");
    
    $success = $stmt->execute([
        $required['product_name'],
        $required['description'],
        $required['category_id'],
        $required['price'],
        $required['quantity'],
        $required['id']
    ]);
    
    if ($success) {
        echo json_encode([
            'status' => 'success',
            'message' => 'product updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update product');
    }
}

function delete_product($conn) {
    if (empty($_POST['id'])) {
        throw new Exception('Product ID is required');
    }

    $product_id = $_POST['id'];

    try {
        $conn->beginTransaction();

        // 1. Delete sales related to this product
        $stmtSales = $conn->prepare("DELETE FROM sales WHERE product_id = ?");
        $stmtSales->execute([$product_id]);

        // 2. Delete purchases related to this product
        $stmtPurchases = $conn->prepare("DELETE FROM purchases WHERE product_id = ?");
        $stmtPurchases->execute([$product_id]);

        // 3. Delete the product itself
        $stmtProduct = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $success = $stmtProduct->execute([$product_id]);

        if ($success) {
            $conn->commit();
            echo json_encode([
                'status' => 'success',
                'message' => 'Product and related sales & purchases deleted successfully'
            ]);
        } else {
            $conn->rollBack();
            throw new Exception('Failed to delete product');
        }
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
?>