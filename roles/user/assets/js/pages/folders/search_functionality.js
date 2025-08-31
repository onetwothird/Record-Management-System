// Department search functionality
function initializeDepartmentSearch() {
    const departmentSearch = document.getElementById('departmentSearch');
    if (departmentSearch) {
        departmentSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            
            // Search within categories and files
            document.querySelectorAll('.category-item').forEach(categoryItem => {
                const categoryName = categoryItem.querySelector('.category-name')?.textContent.toLowerCase() || '';
                let hasMatchingFiles = false;
                
                // Check if category name matches
                const categoryMatches = categoryName.includes(searchTerm);
                
                // Check files within this category
                categoryItem.querySelectorAll('.file-card').forEach(card => {
                    const fileName = card.querySelector('.file-name')?.textContent.toLowerCase() || '';
                    const uploader = card.querySelector('.file-uploader')?.textContent.toLowerCase() || '';
                    const description = card.querySelector('div[style*="background: #f8fafc"]')?.textContent.toLowerCase() || '';
                    
                    const fileMatches = fileName.includes(searchTerm) || uploader.includes(searchTerm) || description.includes(searchTerm);
                    
                    if (fileMatches) {
                        card.style.display = 'block';
                        hasMatchingFiles = true;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // Show/hide category based on matches
                if (categoryMatches || hasMatchingFiles || searchTerm === '') {
                    categoryItem.style.display = 'block';
                } else {
                    categoryItem.style.display = 'none';
                }
            });
            
            // Also filter department items
            document.querySelectorAll('.department-item').forEach(item => {
                const deptName = item.querySelector('.department-name')?.textContent.toLowerCase() || '';
                const deptCode = item.querySelector('.department-code')?.textContent.toLowerCase() || '';
                
                if (deptName.includes(searchTerm) || deptCode.includes(searchTerm) || searchTerm === '') {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
}