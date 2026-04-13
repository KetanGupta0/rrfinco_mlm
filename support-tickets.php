<?php
/**
 * BachatPay - Support Tickets Page
 * Text-only support queries with open/closed status (closed cannot reopen)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

$displayName = trim((($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if (empty($displayName)) {
    $displayName = $user['username'];
}

$csrf_token = generateCSRFToken();
$action = $_GET['action'] ?? 'list';
$ticket_id = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
$error = '';
$success = '';

// Handle new ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $priority = $_POST['priority'] ?? 'medium';
        
        if (empty($subject) || strlen($subject) < 5) {
            $error = 'Subject must be at least 5 characters';
        } elseif (empty($message) || strlen($message) < 10) {
            $error = 'Message must be at least 10 characters';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO support_tickets (user_id, subject, message, category, priority, status)
                    VALUES (?, ?, ?, ?, ?, "open")
                ');
                $stmt->execute([$user['user_id'], $subject, $message, $category, $priority]);
                $success = 'Ticket created successfully. Ticket ID: #' . $pdo->lastInsertId();
                $action = 'list';
            } catch (Exception $e) {
                $error = 'Error creating ticket. Please try again.';
            }
        }
    }
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_ticket']) && $ticket_id > 0) {
    // Verify ownership
    $stmt = $pdo->prepare('SELECT user_id, status FROM support_tickets WHERE ticket_id = ?');
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket || $ticket['user_id'] != $user['user_id']) {
        $error = 'Ticket not found';
    } elseif ($ticket['status'] === 'closed') {
        $error = 'Cannot reply to closed tickets';
    } elseif (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $reply_message = trim($_POST['reply_message'] ?? '');
        
        if (empty($reply_message) || strlen($reply_message) < 5) {
            $error = 'Reply must be at least 5 characters';
        } else {
            try {
                $stmt = $pdo->prepare('
                    INSERT INTO support_replies (ticket_id, sender_id, message, is_admin_reply)
                    VALUES (?, ?, ?, 0)
                ');
                $stmt->execute([$ticket_id, $user['user_id'], $reply_message]);
                $success = 'Reply sent successfully';
            } catch (Exception $e) {
                $error = 'Error sending reply. Please try again.';
            }
        }
    }
}

// Handle close ticket
if (isset($_GET['confirm']) && $_GET['action'] === 'close' && $ticket_id > 0) {
    $stmt = $pdo->prepare('SELECT user_id, status FROM support_tickets WHERE ticket_id = ?');
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if ($ticket && $ticket['user_id'] == $user['user_id'] && $ticket['status'] !== 'closed') {
        $stmt = $pdo->prepare('UPDATE support_tickets SET status = "closed" WHERE ticket_id = ?');
        $stmt->execute([$ticket_id]);
        $success = 'Ticket closed successfully';
        $action = 'list';
    }
}

// Get tickets
if ($action === 'list') {
    $status_filter = $_GET['status'] ?? 'all';
    $where = 'user_id = ?';
    $params = [$user['user_id']];
    
    if ($status_filter !== 'all') {
        $where .= ' AND status = ?';
        $params[] = $status_filter;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM support_tickets WHERE $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
    
    $open_count = 0;
    $closed_count = 0;
    foreach ($tickets as $t) {
        if ($t['status'] === 'open') $open_count++;
        else $closed_count++;
    }
}

// Get single ticket with replies
if ($action === 'view' && $ticket_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE ticket_id = ? AND user_id = ?');
    $stmt->execute([$ticket_id, $user['user_id']]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        $error = 'Ticket not found';
        $action = 'list';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM support_replies WHERE ticket_id = ? ORDER BY created_at ASC');
        $stmt->execute([$ticket_id]);
        $replies = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Tickets - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'support-tickets'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <?php if ($action === 'create'): ?>
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-plus-circle text-blue-600 mr-3"></i>Create Support Ticket
            </h1>
        </div>
        <?php elseif ($action === 'view' && !empty($ticket)): ?>
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-ticket-alt text-blue-600 mr-3"></i>Ticket #<?php echo $ticket['ticket_id']; ?>
            </h1>
        </div>
        <?php else: ?>
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-headset text-blue-600 mr-3"></i>Support Tickets
            </h1>
            <p class="text-gray-600">Contact our support team for assistance</p>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <?php endif; ?>

        <!-- Create Ticket Form -->
        <?php if ($action === 'create'): ?>
        <div class="bg-white rounded-lg shadow p-8 max-w-2xl">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="create_ticket" value="1">
                
                <!-- Subject -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Subject *</label>
                    <input type="text" name="subject" minlength="5" maxlength="200" required placeholder="Brief description of your issue"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Min 5 characters</p>
                </div>

                <!-- Category -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                    <select name="category" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="general">General Inquiry</option>
                        <option value="billing">Billing Issue</option>
                        <option value="technical">Technical Support</option>
                        <option value="account">Account Issue</option>
                        <option value="withdrawal">Withdrawal Problem</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Priority -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Priority</label>
                    <select name="priority" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <!-- Message -->
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Message *</label>
                    <textarea name="message" minlength="10" maxlength="5000" rows="8" required placeholder="Describe your issue in detail (text only, no files)"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Min 10 characters | Text only (no media files)</p>
                </div>

                <!-- Info -->
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg mb-6">
                    <p class="text-sm text-blue-800">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Note:</strong> Only text is allowed in support tickets. Media files cannot be uploaded.
                    </p>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-4">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                        <i class="fas fa-paper-plane mr-2"></i>Submit Ticket
                    </button>
                    <a href="support-tickets.php" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded transition text-center">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- View Ticket -->
        <?php elseif ($action === 'view' && !empty($ticket)): ?>
        <div class="max-w-4xl mx-auto">
            <!-- Ticket Header -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="flex justify-between items-start mb-6 pb-6 border-b">
                    <div>
                        <p class="text-gray-600 text-sm">Ticket ID</p>
                        <p class="text-3xl font-bold text-gray-900">#<?php echo $ticket['ticket_id']; ?></p>
                    </div>
                    <div class="text-right">
                        <span class="px-4 py-2 rounded-full font-semibold <?php 
                            echo $ticket['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                        ?>">
                            <?php echo ucfirst($ticket['status']); ?>
                        </span>
                    </div>
                </div>

                <h2 class="text-2xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($ticket['subject']); ?></h2>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <p class="text-gray-600 text-sm">Category</p>
                        <p class="font-semibold text-gray-900"><?php echo ucfirst(htmlspecialchars($ticket['category'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Priority</p>
                        <p class="font-semibold text-gray-900"><?php echo ucfirst(htmlspecialchars($ticket['priority'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Created</p>
                        <p class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($ticket['created_at'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm">Responses</p>
                        <p class="font-semibold text-gray-900"><?php echo count($replies); ?></p>
                    </div>
                </div>

                <!-- Ticket Message -->
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['message']); ?></p>
                </div>
            </div>

            <!-- Replies -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-comments text-blue-600 mr-2"></i>Ticket Conversation
                </h3>

                <?php if (empty($replies)): ?>
                <p class="text-gray-600 text-center py-8">No replies yet. Our team will respond soon.</p>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($replies as $reply): 
                        $sender_name = $reply['is_admin_reply'] ? 'BachatPay Support' : htmlspecialchars($displayName);
                        $sender_badge = $reply['is_admin_reply'] ? '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-semibold ml-2">Admin</span>' : '';
                    ?>
                    <div class="bg-gray-50 p-4 rounded-lg border-l-4 <?php echo $reply['is_admin_reply'] ? 'border-blue-500' : 'border-gray-300'; ?>">
                        <div class="flex justify-between items-start mb-2">
                            <p class="font-bold text-gray-900">
                                <?php echo $sender_name; ?>
                                <?php echo $sender_badge; ?>
                            </p>
                            <span class="text-xs text-gray-500"><?php echo date('M d, Y H:i', strtotime($reply['created_at'])); ?></span>
                        </div>
                        <p class="text-gray-700 whitespace-pre-wrap"><?php echo htmlspecialchars($reply['message']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Reply Form (Only if ticket is open) -->
            <?php if ($ticket['status'] === 'open'): ?>
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-xl font-bold text-gray-900 mb-4">Add Reply</h3>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="reply_ticket" value="1">
                    
                    <textarea name="reply_message" minlength="5" maxlength="2000" rows="6" required placeholder="Type your reply here..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 mb-4"></textarea>
                    
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition">
                        <i class="fas fa-reply mr-2"></i>Send Reply
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-red-50 border border-red-200 p-6 rounded-lg text-red-800">
                <i class="fas fa-lock mr-2"></i>
                <strong>This ticket is closed.</strong> Closed tickets cannot be reopened or replied to. Please create a new ticket if you have additional questions.
            </div>
            <?php endif; ?>

            <!-- Close Button -->
            <?php if ($ticket['status'] === 'open'): ?>
            <div class="text-center pt-6">
                <a href="?action=close&ticket_id=<?php echo $ticket['ticket_id']; ?>&confirm=1" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition"
                    onclick="return confirm('Are you sure you want to close this ticket? Closed tickets cannot be reopened.');">
                    <i class="fas fa-times-circle mr-2"></i>Close Ticket
                </a>
            </div>
            <?php endif; ?>

            <!-- Back Button -->
            <div class="text-center mt-8">
                <a href="support-tickets.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tickets
                </a>
            </div>
        </div>

        <!-- Tickets List -->
        <?php else: ?>
        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Open Tickets</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $open_count ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-ticket-alt text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Closed Tickets</p>
                        <p class="text-3xl font-bold text-gray-600 mt-2"><?php echo $closed_count ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check-circle text-gray-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Tickets</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo count($tickets ?? []); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-list text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Create Button -->
        <div class="bg-white rounded-lg shadow p-6 mb-8 flex justify-between items-center flex-wrap gap-4">
            <form method="GET" action="" class="flex-1">
                <select name="status" onchange="this.form.submit()" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="all" <?php echo ($status_filter ?? 'all') === 'all' ? 'selected' : ''; ?>>All Tickets</option>
                    <option value="open" <?php echo ($status_filter ?? 'all') === 'open' ? 'selected' : ''; ?>>Open Only</option>
                    <option value="closed" <?php echo ($status_filter ?? 'all') === 'closed' ? 'selected' : ''; ?>>Closed Only</option>
                </select>
            </form>
            <a href="?action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-plus mr-2"></i>New Ticket
            </a>
        </div>

        <!-- Tickets Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Ticket ID</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Subject</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Category</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Created</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                                No tickets found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($tickets as $t): 
                                $statusColor = $t['status'] === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-6 py-3 font-bold text-blue-600">#<?php echo $t['ticket_id']; ?></td>
                                <td class="px-6 py-3">
                                    <p class="font-semibold text-gray-900"><?php echo htmlspecialchars(substr($t['subject'], 0, 40)); ?></p>
                                    <p class="text-xs text-gray-600">Priority: <?php echo ucfirst($t['priority']); ?></p>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600"><?php echo ucfirst($t['category']); ?></td>
                                <td class="px-6 py-3 text-sm text-gray-600"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></td>
                                <td class="px-6 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                        <?php echo ucfirst($t['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <a href="?action=view&ticket_id=<?php echo $t['ticket_id']; ?>" class="text-blue-600 hover:text-blue-800 font-semibold">
                                        View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
