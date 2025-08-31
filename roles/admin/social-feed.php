<?php
// Start session at the very beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Check if user is logged in and is admin
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed - Admin - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- Component Stylesheets -->
    <link rel="stylesheet" href="assets/css/base.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/social_feed.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/social_feed/social-feed.css?v=<?= time() ?>">
</head>

<body>
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->
        <?php include 'components/navbar.html'; ?>

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Social Feed - Admin</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Social Feed Management</a></li>
                    </ul>
                </div>
                <div class="admin-stats">
                    <div class="stat-card">
                        <i class='bx bx-message-dots'></i>
                        <div>
                            <span id="totalPosts">0</span>
                            <p>Total Posts</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class='bx bx-hide'></i>
                        <div>
                            <span id="hiddenPosts">0</span>
                            <p>Hidden Posts</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class='bx bx-group'></i>
                        <div>
                            <span id="activeUsers">0</span>
                            <p>Active Users</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="feed-container">
                <!-- ADMIN CONTROLS -->
                <div class="admin-controls">
                    <div class="control-group">
                        <h3>Moderation Tools</h3>
                        <div class="control-buttons">
                            <button class="control-btn" onclick="showHiddenPosts()">
                                <i class='bx bx-hide'></i> View Hidden Posts
                            </button>
                            <button class="control-btn" onclick="exportPostData()">
                                <i class='bx bx-export'></i> Export Data
                            </button>
                            <button class="control-btn" onclick="showModerationLog()">
                                <i class='bx bx-history'></i> Moderation Log
                            </button>
                        </div>
                    </div>
                    
                    <div class="control-group">
                        <h3>Quick Filters</h3>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">
                                <i class='bx bx-home'></i> All Posts
                            </button>
                            <button class="filter-btn" data-filter="reported">
                                <i class='bx bx-flag'></i> Reported Content
                            </button>
                            <button class="filter-btn" data-filter="department">
                                <i class='bx bx-buildings'></i> My Department
                            </button>
                        </div>
                    </div>
                </div>

                <!-- TOP ROW - Navigation -->
                <div class="feed-navigation">
                    <div class="feed-tabs">
                        <button class="feed-tab active" data-filter="all">
                            <i class='bx bx-home'></i> All Posts
                        </button>
                        <button class="feed-tab" data-filter="my_posts">
                            <i class='bx bx-user'></i> My Posts
                        </button>
                        <button class="feed-tab" data-filter="department">
                            <i class='bx bx-buildings'></i> Departments
                        </button>
                        <button class="feed-tab" data-filter="pinned">
                            <i class='bx bx-pin'></i> Pinned
                        </button>
                    </div>
                </div>

                <!-- CONTENT AREA -->
                <div class="feed-content-area" id="feedContentArea">
                    <!-- Main Column - Posts and Composer -->
                    <div class="feed-main-column">
                        <!-- Post Composer -->
                        <div class="post-composer">
                            <form id="postForm" onsubmit="createPost(event)">
                                <div class="composer-header">
                                    <img src="assets/img/default-avatar.png" alt="Your avatar" class="composer-avatar"
                                        id="composerAvatar">

                                    <div class="composer-user-info">
                                        <strong id="composerName"><?= $_SESSION['username'] ?></strong>

                                        <div class="visibility-selector">
                                            <button type="button" class="visibility-btn" id="visibilityBtn">
                                                <i class='bx bx-globe'></i> Everyone
                                                <i class='bx bx-chevron-down'></i>
                                            </button>

                                            <div class="visibility-dropdown" id="visibilityDropdown">
                                                <div class="visibility-option" data-visibility="public">
                                                    <i class='bx bx-globe'></i>
                                                    <div>
                                                        <strong>Everyone</strong>
                                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                                            Visible to all users
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="visibility-option" data-visibility="department">
                                                    <i class='bx bx-buildings'></i>
                                                    <div>
                                                        <strong>Department</strong>
                                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                                            Only your department
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="visibility-option" data-visibility="custom">
                                                    <i class='bx bx-group'></i>
                                                    <div>
                                                        <strong>Specific Users</strong>
                                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                                            Choose who can see
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <textarea class="composer-textarea" id="postContent"
                                    placeholder="Post an announcement or update..." required></textarea>

                                <div class="composer-actions">
                                    <div class="composer-tools">
                                        <button type="button" class="composer-tool" title="Add Image"
                                            onclick="document.getElementById('imageInput').click()">
                                            <i class='bx bx-image'></i>
                                        </button>
                                        <button type="button" class="composer-tool" title="Add File"
                                            onclick="document.getElementById('fileInput').click()">
                                            <i class='bx bx-paperclip'></i>
                                        </button>
                                        <button type="button" class="composer-tool" title="Pin Post"
                                            id="pinPostBtn" onclick="togglePinPost()">
                                            <i class='bx bx-pin'></i>
                                        </button>
                                    </div>

                                    <button type="submit" class="post-btn" id="postBtn" disabled>
                                        <i class='bx bx-send'></i> Post as Admin
                                    </button>
                                </div>

                                <!-- Hidden Inputs -->
                                <input type="file" id="imageInput" accept="image/*" multiple style="display: none;"
                                    onchange="handleImageUpload(this)">
                                <input type="file" id="fileInput" multiple style="display: none;"
                                    onchange="handleFileUpload(this)">
                                <input type="hidden" id="postVisibility" value="public">
                                <input type="hidden" id="postPinned" value="0">
                            </form>
                        </div>

                        <!-- Posts Feed -->
                        <div class="feed-content" id="feedContent">
                            <div class="loading">
                                <div class="spinner"></div>
                                <p>Loading posts...</p>
                            </div>
                        </div>

                        <!-- Load More -->
                        <div class="load-more" id="loadMoreSection" style="display: none;">
                            <button class="load-more-btn" onclick="loadMorePosts()">
                                <i class='bx bx-refresh'></i> Load More Posts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </section>

    <!-- Moderation Modal -->
    <div class="modal" id="moderationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Moderate Post</h3>
                <span class="close" onclick="closeModal('moderationModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div id="moderationPostPreview"></div>
                <div class="form-group">
                    <label for="moderationAction">Action:</label>
                    <select id="moderationAction" onchange="toggleReasonField()">
                        <option value="hide">Hide Post</option>
                        <option value="show">Show Post</option>
                        <option value="delete">Delete Post</option>
                    </select>
                </div>
                <div class="form-group" id="reasonField">
                    <label for="moderationReason">Reason:</label>
                    <textarea id="moderationReason" placeholder="Enter reason for moderation..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('moderationModal')">Cancel</button>
                <button class="btn-primary" onclick="submitModeration()">Submit</button>
            </div>
        </div>
    </div>

    <!-- Scripts - Load in order -->
    <script src="assets/js/script.js?v=<?= time() ?>"></script>

    <!-- Social Feed Modules -->
    <script src="assets/js/social_feed/utilities.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/core.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/post_composer.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/post_renderer.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/post_actions.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/comment_system.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/media_handler.js?v=<?= time() ?>"></script>
    <script src="assets/js/social_feed/main.js?v=<?= time() ?>"></script>
    <script src="assets/js/script.js?v=<?= time() ?>"></script>

    <!-- Page-specific initialization -->
    <script>
        // Set active sidebar item and page title for admin social feed
        document.addEventListener('DOMContentLoaded', function () {
            // Set active sidebar item
            if (window.AppUtils) {
                window.AppUtils.setActiveSidebarItem('social-feed.php');
                window.AppUtils.updateNavbarTitle('Social Feed - Admin');
            }
            
            // Load admin stats
            loadAdminStats();
            
            // Initialize filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    loadPosts(0, this.dataset.filter);
                });
            });
        });
        
        // Admin specific functions
        function loadAdminStats() {
            fetch('api/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('totalPosts').textContent = data.total_posts;
                        document.getElementById('hiddenPosts').textContent = data.hidden_posts;
                        document.getElementById('activeUsers').textContent = data.active_users;
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }
        
        function showHiddenPosts() {
            // Implementation to show hidden posts
            console.log('Showing hidden posts');
        }
        
        function exportPostData() {
            // Implementation to export data
            console.log('Exporting post data');
        }
        
        function showModerationLog() {
            // Implementation to show moderation log
            console.log('Showing moderation log');
        }
        
        function togglePinPost() {
            const pinBtn = document.getElementById('pinPostBtn');
            const pinInput = document.getElementById('postPinned');
            const isPinned = pinInput.value === '1';
            
            if (isPinned) {
                pinBtn.classList.remove('active');
                pinInput.value = '0';
            } else {
                pinBtn.classList.add('active');
                pinInput.value = '1';
            }
        }
        
        function moderatePost(postId) {
            // Show moderation modal
            document.getElementById('moderationModal').style.display = 'block';
            
            // Load post preview
            fetch(`api/get_post.php?id=${postId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('moderationPostPreview').innerHTML = `
                            <div class="post-preview">
                                <strong>Post by:</strong> ${data.post.author_full_name}<br>
                                <strong>Content:</strong> ${data.post.content.substring(0, 100)}...
                            </div>
                        `;
                    }
                });
            
            // Store post ID for submission
            document.getElementById('moderationModal').dataset.postId = postId;
        }
        
        function toggleReasonField() {
            const action = document.getElementById('moderationAction').value;
            const reasonField = document.getElementById('reasonField');
            
            if (action === 'show') {
                reasonField.style.display = 'none';
            } else {
                reasonField.style.display = 'block';
            }
        }
        
        function submitModeration() {
            const postId = document.getElementById('moderationModal').dataset.postId;
            const action = document.getElementById('moderationAction').value;
            const reason = document.getElementById('moderationReason').value;
            
            fetch('api/moderate_post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    post_id: postId,
                    action: action,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal('moderationModal');
                    showNotification('Post moderated successfully', 'success');
                    // Refresh the feed
                    loadPosts();
                } else {
                    showNotification('Error moderating post: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Error moderating post', 'error');
            });
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>

</body>

</html>