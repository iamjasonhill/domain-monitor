/**
 * Dark Mode Toggle
 * 
 * Handles dark mode switching and persistence in localStorage
 */

(function () {
    // Get the current theme from localStorage or default to 'dark'
    const getTheme = () => {
        return localStorage.getItem('theme') || 'dark';
    };

    // Set the theme
    const setTheme = (theme) => {
        localStorage.setItem('theme', theme);
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    // Initialize theme on page load
    const initTheme = () => {
        const theme = getTheme();
        setTheme(theme);
    };

    // Toggle between light and dark
    const toggleTheme = () => {
        const currentTheme = getTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
        return newTheme;
    };

    // Expose functions globally for Livewire/Alpine (before initialization)
    window.toggleDarkMode = toggleTheme;
    window.getDarkMode = () => {
        // If localStorage has a value, use it
        const stored = localStorage.getItem('theme');
        if (stored) {
            return stored === 'dark';
        }
        // Fallback: check if dark class is already on html element
        // (should be set by inline initialization script if defaulting to dark)
        // Return the actual class state - initialization script handles defaulting to dark
        return document.documentElement.classList.contains('dark');
    };
    window.setDarkMode = setTheme;

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
