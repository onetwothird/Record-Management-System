// main.js - Main initialization and global variables

// Global variables
let selectedFiles = [];
let selectedTags = [];
let isUploading = false; // FIX: Add upload state tracking
const userDepartmentId = window.userDepartmentId || null;
const fileCategories = window.fileCategories || {};

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all existing components
    initializeSidebar();
    initializeSearch();
    initializeDarkMode();
    initializeResponsive();
    initializeDepartmentSearch();
    initializeUploadForm();
    initializeModalEvents();
    
    // Initialize new academic period functionality
    initializeSemesterSelection();
    initializeAcademicYearSelection();
    showAcademicPeriodSummary();

    // Auto-load user's department on page load
    if (userDepartmentId) {
        setTimeout(() => {
            toggleDepartment(userDepartmentId);
        }, 500);
    }
});
document.addEventListener('DOMContentLoaded', function() {
    // Initialize UI components
    initializeSidebar();
    initializeSearch();
    initializeDarkMode();
    initializeResponsive();
    
    // Initialize search functionality
    initializeDepartmentSearch();
    
    // Initialize upload functionality
    initializeUploadForm();
    initializeModalEvents();
    
    // Any other initialization code can go here
    console.log('File management system initialized successfully');
});
// Export functions to window object for global access
window.populateAcademicYears = populateAcademicYears;
window.getCurrentAcademicYear = getCurrentAcademicYear;
window.getCurrentSemester = getCurrentSemester;
window.validateAcademicPeriod = validateAcademicPeriod;
window.openUploadModal = openUploadModal;
window.closeUploadModal = closeUploadModal;
window.handleDrop = handleDrop;
window.removeFile = removeFile;
window.addTag = addTag;
window.addCustomTag = addCustomTag;
window.removeTag = removeTag;
window.toggleDepartment = toggleDepartment;
window.toggleCategory = toggleCategory;
window.showCategorySemester = showCategorySemester;
window.downloadFile = downloadFile;