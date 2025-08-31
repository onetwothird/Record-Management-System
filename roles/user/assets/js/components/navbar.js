// Enhanced Navbar functionality with better conflict handling
(function() {
    'use strict';
    
    let initialized = false;
    
    function initializeNavbar() {
        if (initialized) {
            console.log('Navbar already initialized, skipping...');
            return;
        }
        
        console.log('Initializing navbar functionality...');
        
        // Sidebar menu items activation
        const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');
        console.log('Found sidebar menu items:', allSideMenu.length);

        allSideMenu.forEach(item => {
            const li = item.parentElement;

            item.addEventListener('click', function () {
                allSideMenu.forEach(i => {
                    i.parentElement.classList.remove('active');
                });
                li.classList.add('active');
            });
        });

        // Toggle sidebar - Enhanced version
        const menuBar = document.querySelector('#content nav .bx.bx-menu');
        const sidebar = document.getElementById('sidebar');

        console.log('Menu bar element:', menuBar);
        console.log('Sidebar element:', sidebar);

        if (menuBar && sidebar) {
            // Remove any existing listeners first
            const newMenuBar = menuBar.cloneNode(true);
            menuBar.parentNode.replaceChild(newMenuBar, menuBar);
            
            newMenuBar.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Menu button clicked - toggling sidebar');
                
                sidebar.classList.toggle('hide');
                
                // Add smooth transition if not already present
                if (!sidebar.style.transition) {
                    sidebar.style.transition = 'margin-left 0.3s ease';
                }
                
                console.log('Sidebar classes after toggle:', sidebar.className);
                
                // Force reflow for animation
                sidebar.offsetHeight;
            });
            
            console.log('Menu toggle event listener added successfully');
        } else {
            console.error('Menu elements not found:', { menuBar, sidebar });
        }

        // Dark mode switch
        const switchMode = document.getElementById('switch-mode');
        console.log('Switch mode element:', switchMode);

        if (switchMode) {
            if(localStorage.getItem('mode') === 'dark') {
                switchMode.checked = true;
                document.body.classList.add('dark');
            }

            switchMode.addEventListener('change', function () {
                if (this.checked) {
                    document.body.classList.add('dark');
                    localStorage.setItem('mode', 'dark');
                } else {
                    document.body.classList.remove('dark');
                    localStorage.setItem('mode', 'light');
                }
            });
            console.log('Dark mode toggle initialized');
        } else {
            console.warn('Dark mode switch not found');
        }
        
        // Window resize handler for responsive behavior
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && window.innerWidth <= 768) {
                sidebar.classList.add('hide');
            }
        });
        
        initialized = true;
        console.log('Navbar initialization complete');
    }

    // Multiple initialization attempts to handle different loading scenarios
    
    // Try immediate initialization
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNavbar);
    } else {
        initializeNavbar();
    }

    // Backup initialization on window load
    window.addEventListener('load', function() {
        if (!initialized) {
            console.log('Backup initialization triggered');
            setTimeout(initializeNavbar, 100);
        }
    });
    
    // Emergency fallback with delay
    setTimeout(function() {
        if (!initialized) {
            console.log('Emergency fallback initialization');
            initializeNavbar();
        }
    }, 500);
    
    // Global function to force re-initialization if needed
    window.forceNavbarInit = function() {
        initialized = false;
        initializeNavbar();
    };
    
})();