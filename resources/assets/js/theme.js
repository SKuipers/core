/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * ThemeManager - Manages dark mode theme preferences and application
 */
class ThemeManager {
    constructor() {
        this.STORAGE_KEY = 'gibbon-theme-preference';
        this.THEME_LIGHT = 'light';
        this.THEME_DARK = 'dark';
        this.THEME_AUTO = 'auto';
        
        this.init();
    }

    /**
     * Initialize the theme manager
     */
    init() {
        // Apply the current theme
        this.applyTheme();
        
        // Listen for system theme changes
        this.listenForSystemThemeChanges();
    }

    /**
     * Get the user's theme preference from localStorage
     * @returns {string} 'light', 'dark', or 'auto'
     */
    getPreference() {
        const stored = localStorage.getItem(this.STORAGE_KEY);
        if (stored === this.THEME_LIGHT || stored === this.THEME_DARK || stored === this.THEME_AUTO) {
            return stored;
        }
        return this.THEME_AUTO; // Default to auto
    }

    /**
     * Set the user's theme preference
     * @param {string} preference - 'light', 'dark', or 'auto'
     */
    setPreference(preference) {
        if (preference !== this.THEME_LIGHT && preference !== this.THEME_DARK && preference !== this.THEME_AUTO) {
            console.error('Invalid theme preference:', preference);
            return;
        }
        
        localStorage.setItem(this.STORAGE_KEY, preference);
        this.applyTheme();
    }

    /**
     * Detect if the system prefers dark mode
     * @returns {boolean}
     */
    systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    /**
     * Get the effective theme (resolving 'auto' to 'light' or 'dark')
     * @returns {string} 'light' or 'dark'
     */
    getEffectiveTheme() {
        const preference = this.getPreference();
        
        if (preference === this.THEME_AUTO) {
            return this.systemPrefersDark() ? this.THEME_DARK : this.THEME_LIGHT;
        }
        
        return preference;
    }

    /**
     * Apply the current theme to the document
     */
    applyTheme() {
        const effectiveTheme = this.getEffectiveTheme();
        
        if (effectiveTheme === this.THEME_DARK) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    /**
     * Listen for system theme changes and apply them if preference is 'auto'
     */
    listenForSystemThemeChanges() {
        if (!window.matchMedia) return;
        
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        // Modern browsers
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', () => {
                if (this.getPreference() === this.THEME_AUTO) {
                    this.applyTheme();
                }
            });
        } 
        // Older browsers
        else if (mediaQuery.addListener) {
            mediaQuery.addListener(() => {
                if (this.getPreference() === this.THEME_AUTO) {
                    this.applyTheme();
                }
            });
        }
    }

    /**
     * Toggle between light and dark themes
     */
    toggle() {
        const current = this.getEffectiveTheme();
        const newTheme = current === this.THEME_DARK ? this.THEME_LIGHT : this.THEME_DARK;
        this.setPreference(newTheme);
    }
}

// Export to window object for global access
window.ThemeManager = ThemeManager;

// Auto-initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.themeManager = new ThemeManager();
    });
} else {
    window.themeManager = new ThemeManager();
}
