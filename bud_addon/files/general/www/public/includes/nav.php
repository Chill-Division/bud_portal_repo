<?php
// includes/nav.php

$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page, $current)
{
    return $page === $current ? 'active' : '';
}
?>

<nav class="glass-nav">
    <div class="nav-container">
        <a href="index.php" class="logo">
            <!-- Icon could go here -->
            <?= defined('APP_NAME') ? APP_NAME : 'STASH' ?>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="mobile-toggle" id="navToggle"
            style="display: none; background: transparent; color: var(--text-color); font-size: 1.5rem;">
            ☰
        </button>

        <ul id="navMenu">
            <li><a href="index.php" class="<?= isActive('index.php', $current_page) ?>">Dashboard</a></li>
            <li><a href="suppliers.php" class="<?= isActive('suppliers.php', $current_page) ?>">Suppliers</a></li>
            <li><a href="stock.php" class="<?= isActive('stock.php', $current_page) ?>">Stock</a></li>
            <li><a href="custody.php" class="<?= isActive('custody.php', $current_page) ?>">Chain of Custody</a></li>
            <li><a href="timesheet.php" class="<?= isActive('timesheet.php', $current_page) ?>">Time Sheet</a></li>
            <li><a href="cleaning.php" class="<?= isActive('cleaning.php', $current_page) ?>">Cleaning</a></li>
            <li><a href="reports.php" class="<?= isActive('reports.php', $current_page) ?>">Reports</a></li>
            <li>
                <button id="themeToggle"
                    style="padding: 0.5rem; background: transparent; border: 1px solid var(--text-color); color: var(--text-color);">
                    🌙/☀️
                </button>
            </li>
        </ul>
    </div>
</nav>

<script>
    // Theme Toggling Logic
    const themeToggle = document.getElementById('themeToggle');
    const htmlInfo = document.documentElement;

    // Check local storage or preference
    const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    htmlInfo.setAttribute('data-theme', currentTheme);

    themeToggle.addEventListener('click', () => {
        let theme = htmlInfo.getAttribute('data-theme');
        let newTheme = theme === 'light' ? 'dark' : 'light';
        htmlInfo.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
    });

    // Mobile Menu Toggle
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');

    // Simple display toggle for mobile (CSS should handle media queries to hide/show 'mobile-toggle')
    // We need to ensure CSS handles the 'display: none' for desktop correctly for the button.

    // Adjusting CSS logic in JS for brevity in this snippet, ideally strict CSS.
    // Assuming style.css handles the media query for #navToggle.

    navToggle.addEventListener('click', () => {
        if (navMenu.style.display === 'flex') {
            navMenu.style.display = ''; // Revert to css
        } else {
            navMenu.style.display = 'flex';
            navMenu.style.flexDirection = 'column';
            navMenu.style.position = 'absolute';
            navMenu.style.top = '60px';
            navMenu.style.left = '0';
            navMenu.style.right = '0';
            navMenu.style.background = 'var(--nav-bg)';
            navMenu.style.padding = '1rem';
            navMenu.style.zIndex = '100';
        }
    });
</script>