<!-- file path: user/social_feed.php -->

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Social Feed - CVSU NAIC</title>
    <link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
    <!-- Component Stylesheets -->
    <link rel="stylesheet" href="assets/css/components/sidebar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/components/navbar.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../social_feed/css/social_feed.css?v=<?= time() ?>">
</head>

<body>
    <!-- Sidebar Component -->
    <?php include 'components/sidebar.html'; ?>

    <!-- Content -->
    <section id="content">
        <!-- Navbar Component -->

        <!-- Main Content -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Social Feed</h1>
                    <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Overview</a></li>
                    </ul>
                </div>
            </div>

            <div class="feed-container">
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

                <!-- CONTENT AREA - Single column layout (no side panel) -->
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
                                        <strong id="composerName">Loading...</strong>

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
                                    placeholder="What's happening in your department?" required></textarea>

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
                                    </div>

                                    <button type="submit" class="post-btn" id="postBtn" disabled>
                                        <i class='bx bx-send'></i> Post
                                    </button>
                                </div>

                                <!-- Hidden Inputs -->
                                <input type="file" id="imageInput" accept="image/*" multiple style="display: none;"
                                    onchange="handleImageUpload(this)">
                                <input type="file" id="fileInput" multiple style="display: none;"
                                    onchange="handleFileUpload(this)">
                                <input type="hidden" id="postVisibility" value="public">
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

    <!-- Scripts - Load in order -->
    <script src="assets/js/script.js?v=<?= time() ?>"></script>

    <!-- Social Feed Modules -->
    <script src="social_feed/js/utilities.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/core.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/post_composer.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/post_renderer.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/post_actions.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/comment_system.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/media_handler.js?v=<?= time() ?>"></script>
    <script src="social_feed/js/main.js?v=<?= time() ?>"></script>


    <!-- Page-specific initialization -->
    <script>
        // Override the toggleComments function to use modal instead of side panel
        function toggleComments(postId) {
            if (window.commentSystem && window.commentSystem.openCommentsModal) {
                window.commentSystem.openCommentsModal(postId);
            } else {
                console.warn('Comment system not ready, trying again...');
                setTimeout(() => {
                    if (window.commentSystem && window.commentSystem.openCommentsModal) {
                        window.commentSystem.openCommentsModal(postId);
                    }
                }, 100);
            }
        }

        // Set active sidebar item and page title for social feed
        document.addEventListener('DOMContentLoaded', function () {
            // Set active sidebar item
            if (window.AppUtils) {
                window.AppUtils.setActiveSidebarItem('social_feed.php');
                window.AppUtils.updateNavbarTitle('Social Feed');
            }

            // Initialize search for social feed if needed
            const navbarSearchForm = document.getElementById('navbarSearchForm');
            if (navbarSearchForm) {
                navbarSearchForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const searchInput = document.getElementById('navbarSearchInput');
                    if (searchInput && searchInput.value.trim()) {
                        if (window.searchPosts) {
                            window.searchPosts(e);
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>