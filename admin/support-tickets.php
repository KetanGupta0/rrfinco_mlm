<?php
/**
 * Admin - Support Tickets Management (Enhanced UI)
 * Manage user support tickets: View, Reply, Update Status
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/common.php';

requireAdmin();

$pageTitle = 'Support Tickets Management';
$message = '';
$error = '';
$csrf_token = generateCSRFToken();
$ticket = null;
$replies = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $error = 'Invalid CSRF token';
    } else {
        switch ($action) {
            case 'reply':
                handleReplyTicket($pdo);
                break;
            case 'update_status':
                handleUpdateStatus($pdo);
                break;
        }
    }
}

// Get tab parameter
$tab = $_GET['tab'] ?? 'list';
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Get all tickets with filters
try {
    $query = '
        SELECT t.*, u.username, u.email,
               (SELECT COUNT(*) FROM support_replies sr WHERE sr.ticket_id = t.ticket_id) AS reply_count
        FROM support_tickets t
        JOIN users u ON u.user_id = t.user_id
        WHERE 1=1
    ';
    $params = [];
    
    if ($status_filter !== 'all') {
        $query .= ' AND t.status = ?';
        $params[] = $status_filter;
    }

    if ($priority_filter !== 'all') {
        $query .= ' AND t.priority = ?';
        $params[] = $priority_filter;
    }

    if ($search !== '') {
        $query .= ' AND (u.username LIKE ? OR u.email LIKE ? OR t.subject LIKE ? OR COALESCE(t.message, "") LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like, $like, $like);
    }
    
    $query .= ' ORDER BY t.created_at DESC';
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    logError('Admin support tickets', $e->getMessage());
    $tickets = [];
    $error = 'Failed to load support tickets';
}

// Get ticket details if viewing specific ticket
if ($tab === 'view' && isset($_GET['ticket_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE ticket_id = ?');
        $stmt->execute([(int)$_GET['ticket_id']]);
        $ticket = $stmt->fetch();
        
        if ($ticket) {
            // Get user info
            $stmt = $pdo->prepare('SELECT user_id, username, email FROM users WHERE user_id = ?');
            $stmt->execute([$ticket['user_id']]);
            $user = $stmt->fetch();
            
            // Get all replies
            $stmt = $pdo->prepare('
                SELECT r.*, u.username
                FROM support_replies r
                LEFT JOIN users u ON u.user_id = r.sender_id
                WHERE r.ticket_id = ?
                ORDER BY r.created_at ASC
            ');
            $stmt->execute([$ticket['ticket_id']]);
            $replies = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        logError('Get ticket details', $e->getMessage());
    }
}

// Priority colors
$priorityColors = [
    'critical' => 'danger',
    'high' => 'warning',
    'medium' => 'info',
    'low' => 'primary'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - BachatPay Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="admin-content">
        <!-- Page Header -->
        <div class="admin-page-header">
            <div class="admin-page-title">
                <h2><i class="fas fa-headset mr-2"></i>Support Tickets Management</h2>
                <p>Manage user support requests and provide assistance</p>
            </div>
            <div class="page-tools screen-only">
                <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- List View -->
        <?php if ($tab === 'list'): ?>
            <!-- Status Tabs -->
            <div class="tabs">
                <a href="?tab=list&status=all" class="tab-btn <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
                    <i class="fas fa-inbox"></i> All Tickets
                </a>
                <a href="?tab=list&status=open" class="tab-btn <?php echo ($status_filter === 'open') ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open"></i> Open
                </a>
                <a href="?tab=list&status=in_progress" class="tab-btn <?php echo ($status_filter === 'in_progress') ? 'active' : ''; ?>">
                    <i class="fas fa-spinner"></i> In Progress
                </a>
                <a href="?tab=list&status=closed" class="tab-btn <?php echo ($status_filter === 'closed') ? 'active' : ''; ?>">
                    <i class="fas fa-check"></i> Closed
                </a>
            </div>

            <div class="card filters-card screen-only">
                <div class="card-header">
                    <div>
                        <h3>Search Tickets</h3>
                        <p>Filter by status, priority, or keywords before opening a ticket thread.</p>
                    </div>
                </div>
                <form method="get" class="filters-form">
                    <input type="hidden" name="tab" value="list">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, email, subject, message">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="all">All</option>
                            <?php foreach (['critical', 'high', 'medium', 'low'] as $priority): ?>
                                <option value="<?php echo $priority; ?>" <?php echo $priority_filter === $priority ? 'selected' : ''; ?>><?php echo ucfirst($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <a href="?tab=list&status=<?php echo htmlspecialchars($status_filter); ?>" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3>Support Tickets</h3>
                    <span class="badge badge-primary"><?php echo count($tickets); ?> Tickets</span>
                </div>

                <?php if (empty($tickets)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-gray-400" style="font-size: 3rem;"></i>
                        <p class="text-gray-500 mt-4">No support tickets found</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Subject</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Replies</th>
                                    <th>Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $t): ?>
                                    <tr>
                                        <td><strong>#<?php echo $t['ticket_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($t['username']); ?><span class="table-subtext"><?php echo htmlspecialchars($t['email']); ?></span></td>
                                        <td><?php echo htmlspecialchars(mb_strimwidth($t['subject'], 0, 50, '...')); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $priorityColors[$t['priority']] ?? 'primary'; ?>">
                                                <?php echo ucfirst(htmlspecialchars($t['priority'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php 
                                                if ($t['status'] === 'open') echo 'badge-info';
                                                elseif ($t['status'] === 'in_progress') echo 'badge-warning';
                                                elseif ($t['status'] === 'closed') echo 'badge-success';
                                                else echo 'badge-primary';
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $t['status'])); ?>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo (int) $t['reply_count']; ?></span></td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($t['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="?tab=view&ticket_id=<?php echo $t['ticket_id']; ?>" class="btn btn-primary btn-small">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Ticket Details View -->
        <?php if ($tab === 'view' && $ticket): ?>
            <div class="card" style="max-width: 900px;">
                <div class="card-header">
                    <div>
                        <h3>Ticket #<?php echo $ticket['ticket_id']; ?> - <?php echo htmlspecialchars($ticket['subject']); ?></h3>
                        <small style="color: var(--color-gray-500);">Created on <?php echo date('F d, Y H:i', strtotime($ticket['created_at'])); ?></small>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <span class="badge badge-<?php echo $priorityColors[$ticket['priority']] ?? 'primary'; ?>">
                            <?php echo ucfirst(htmlspecialchars($ticket['priority'])); ?>
                        </span>
                        <span class="badge <?php 
                            if ($ticket['status'] === 'open') echo 'badge-info';
                            elseif ($ticket['status'] === 'in_progress') echo 'badge-warning';
                            elseif ($ticket['status'] === 'closed') echo 'badge-success';
                            else echo 'badge-primary';
                        ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                        </span>
                    </div>
                </div>

                <!-- User Info -->
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-gray-200); background: var(--color-gray-50);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                        <div>
                            <strong style="color: var(--color-gray-600);">User:</strong> <br>
                            <?php echo htmlspecialchars($user['username'] ?? 'Unknown'); ?><br><small><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
                        </div>
                        <div>
                            <strong style="color: var(--color-gray-600);">Category:</strong> <br>
                            <?php echo htmlspecialchars($ticket['category'] ?? 'General'); ?>
                        </div>
                        <div>
                            <strong style="color: var(--color-gray-600);">Email:</strong> <br>
                            <a href="mailto:<?php echo htmlspecialchars($user['email'] ?? ''); ?>"><?php echo htmlspecialchars($user['email'] ?? 'NA'); ?></a>
                        </div>
                    </div>
                </div>

                <!-- Message -->
                <div style="padding: 1.5rem;">
                    <h4 style="margin-bottom: 0.75rem; font-weight: 600;">Message:</h4>
                    <div style="background: var(--color-gray-50); padding: 1rem; border-radius: 0.5rem; border-left: 4px solid var(--color-primary);">
                        <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                    </div>
                </div>

                <!-- Replies -->
                <?php if (!empty($replies)): ?>
                    <div style="padding: 1.5rem; border-top: 1px solid var(--color-gray-200);">
                        <h4 style="margin-bottom: 1rem; font-weight: 600;">
                            <i class="fas fa-comments"></i> Conversation (<?php echo count($replies); ?> replies)
                        </h4>

                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($replies as $reply): ?>
                                <div style="background: <?php echo $reply['is_admin_reply'] ? 'var(--color-primary)' : 'var(--color-gray-100)'; ?>; padding: 1rem; border-radius: 0.5rem; border-left: 4px solid <?php echo $reply['is_admin_reply'] ? 'var(--color-primary-dark)' : 'var(--color-gray-300)'; ?>;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                        <strong style="color: <?php echo $reply['is_admin_reply'] ? 'white' : 'var(--color-gray-900)'; ?>;">
                                            <?php echo $reply['is_admin_reply'] ? '<i class="fas fa-shield-alt"></i> Admin' : htmlspecialchars($reply['username'] ?? 'User'); ?>
                                        </strong>
                                        <small style="color: <?php echo $reply['is_admin_reply'] ? 'rgba(255,255,255,0.7)' : 'var(--color-gray-600)'; ?>;">
                                            <?php echo date('F d, Y H:i', strtotime($reply['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div style="color: <?php echo $reply['is_admin_reply'] ? 'white' : 'var(--color-gray-700)'; ?>;">
                                        <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reply Form (only if not closed) -->
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <div style="padding: 1.5rem; border-top: 1px solid var(--color-gray-200); background: var(--color-gray-50);">
                        <h4 style="margin-bottom: 1rem; font-weight: 600;">Send Reply</h4>

                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">

                            <div class="form-group">
                                <label class="form-label required">Your Reply</label>
                                <textarea name="reply_text" required class="form-textarea" 
                                          placeholder="Type your response here..."></textarea>
                            </div>

                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Status Update -->
                <div style="padding: 1.5rem; border-top: 1px solid var(--color-gray-200);">
                    <h4 style="margin-bottom: 1rem; font-weight: 600;">Update Status</h4>

                    <form method="POST" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['ticket_id']; ?>">

                        <select name="status" required class="form-select" style="max-width: 200px;">
                            <option value="open" <?php echo ($ticket['status'] === 'open') ? 'selected' : ''; ?>>Open</option>
                            <option value="in_progress" <?php echo ($ticket['status'] === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                            <option value="closed" <?php echo ($ticket['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                        </select>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-sync"></i> Update Status
                        </button>
                    </form>
                </div>

                <!-- Back Button -->
                <div style="padding: 1.5rem; text-align: center; border-top: 1px solid var(--color-gray-200);">
                    <a href="?tab=list" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

<?php

function handleReplyTicket($pdo) {
    global $message, $error;
    
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $reply_text = trim($_POST['reply_text'] ?? '');
    
    if ($ticket_id <= 0) {
        $error = 'Invalid ticket ID';
        return;
    }
    if (empty($reply_text)) {
        $error = 'Reply cannot be empty';
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO support_replies (ticket_id, sender_id, message, is_admin_reply)
            VALUES (?, ?, ?, 1)
        ');
        $stmt->execute([$ticket_id, $_SESSION['user_id'], $reply_text]);
        
        // Update ticket status if it's open
        $stmt = $pdo->prepare('UPDATE support_tickets SET status = "in_progress" WHERE ticket_id = ? AND status = "open"');
        $stmt->execute([$ticket_id]);
        
        $message = 'Reply sent successfully';
        recordAuditLog($_SESSION['user_id'], 'reply_ticket', 'support_tickets', $ticket_id, null, null);
        
        header("Location: support-tickets.php?tab=view&ticket_id=$ticket_id");
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to send reply: ' . $e->getMessage();
        logError('Admin reply ticket', $e->getMessage());
    }
}

function handleUpdateStatus($pdo) {
    global $message, $error;
    
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    
    if ($ticket_id <= 0) {
        $error = 'Invalid ticket ID';
        return;
    }
    if (!in_array($status, ['open', 'in_progress', 'closed'])) {
        $error = 'Invalid status';
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE support_tickets SET status = ? WHERE ticket_id = ?');
        $stmt->execute([$status, $ticket_id]);
        
        $message = 'Ticket status updated successfully';
        recordAuditLog($_SESSION['user_id'], 'update_ticket_status', 'support_tickets', $ticket_id, null, ['status' => $status]);
        
        header("Location: support-tickets.php?tab=view&ticket_id=$ticket_id");
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to update ticket status: ' . $e->getMessage();
        logError('Admin update ticket status', $e->getMessage());
    }
}
?>
