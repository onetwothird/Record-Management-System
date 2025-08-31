// FIX: Enhanced upload form handler with duplicate prevention
function initializeUploadForm() {
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // FIX: Prevent multiple submissions
            if (isUploading) {
                console.log('Upload already in progress, preventing duplicate submission');
                return false;
            }
            
            const category = document.querySelector('select[name="category"]')?.value;
            const academicYear = document.querySelector('select[name="academic_year"]')?.value;
            const semester = document.querySelector('input[name="semester"]:checked')?.value;
            const description = document.getElementById('fileDescription')?.value || '';
            
            // Enhanced validation
            if (!category) {
                alert('Please select a file category.');
                return false;
            }
            
            if (!validateAcademicPeriod()) {
                return false;
            }
            
            if (selectedFiles.length === 0) {
                alert('Please select at least one file to upload.');
                return false;
            }
            
            // FIX: Set uploading state and disable form elements
            isUploading = true;
            disableFormElements(true);
            
            // Show progress
            const uploadProgress = document.getElementById('uploadProgress');
            if (uploadProgress) {
                uploadProgress.style.display = 'block';
            }
            
            const formData = new FormData();
            formData.append('department', userDepartmentId); 
            formData.append('category', category);
            formData.append('academic_year', academicYear);
            formData.append('semester', semester);
            formData.append('description', description);
            formData.append('tags', JSON.stringify(selectedTags));
            
            // FIX: Append files properly
            selectedFiles.forEach((file, index) => {
                formData.append('files[]', file);
            });
            
            // FIX: Enhanced fetch with better error handling
            fetch('handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Show success message with details
                    let message = data.message || 'Files uploaded successfully!';
                    if (data.warnings && data.warnings.length > 0) {
                        message += '\n\nWarnings:\n' + data.warnings.join('\n');
                    }
                    alert(message);
                    
                    closeUploadModal();
                    
                    // Refresh the category counts and files
                    if (userDepartmentId) {
                        loadDepartmentCategories(userDepartmentId);
                        if (data.category) {
                            loadCategoryFiles(userDepartmentId, data.category);
                        }
                    }
                } else {
                    // Show detailed error message
                    let errorMessage = 'Upload failed: ' + (data.message || 'Unknown error');
                    if (data.errors && data.errors.length > 0) {
                        errorMessage += '\n\nDetailed errors:\n' + data.errors.join('\n');
                    }
                    alert(errorMessage);
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                alert('Upload failed. Please check your connection and try again.\n\nError: ' + error.message);
            })
            .finally(() => {
                // FIX: Always reset upload state and re-enable form
                isUploading = false;
                disableFormElements(false);
                
                const uploadProgress = document.getElementById('uploadProgress');
                if (uploadProgress) {
                    uploadProgress.style.display = 'none';
                }
            });
            
            // Simulate progress for visual feedback
            simulateUpload();
            
            return false; // Prevent default form submission
        });
    }
}

// FIX: Helper function to disable/enable form elements during upload
function disableFormElements(disabled) {
    // Disable/enable all form inputs
    const formElements = document.querySelectorAll('#uploadForm input, #uploadForm select, #uploadForm textarea, #uploadForm button');
    formElements.forEach(element => {
        element.disabled = disabled;
        if (disabled) {
            element.style.opacity = '0.6';
            element.style.cursor = 'not-allowed';
        } else {
            element.style.opacity = '1';
            element.style.cursor = '';
        }
    });
    
    // Update file preview to reflect disabled state
    if (selectedFiles.length > 0) {
        displayFilePreview();
    }
}