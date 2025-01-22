<?php

function getCssStyle()
{
    ?>
    <style>
        :root {
            --bg-color: #f0f4f8;
            --text-color: #2e3a59;
            --card-bg: #ffffff;
            --border-color: #d1d9e6;
            --header-bg: #3b82f6;
            --header-text-color: #ffffff;
            --wrapper-bg: #e5eaf1; /* Updated root color for wrapper background */
        }

        [data-theme="dark"] {
            --bg-color: #1f2937;
            --text-color: #e5e7eb;
            --card-bg: #374151;
            --border-color: #4b5563;
            --header-bg: #2563eb;
            --header-text-color: #ffffff;
            --wrapper-bg: #2d3748; /* Updated root color for wrapper background in dark theme */
        }

* {
    transition: background-color var(--transition-speed) ease,
        color var(--transition-speed) ease,
        border-color var(--transition-speed) ease,
        box-shadow var(--transition-speed) ease;
    box-sizing: border-box; /* Added for better element sizing */
}

body {
    font-family: system-ui, -apple-system, sans-serif;
    background: var(--bg-color);
    color: var(--text-color);
    margin: 0;
    padding: 20px;
    transition: background-color 0.3s;
}

#wrapper {
    padding: 20px;
    background: var(--wrapper-bg); /* Use the new root color */
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    max-width: 100%; /* Make wrapper responsive */
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 15px; /* Add padding for better layout on smaller screens */
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    background-color: var(--header-bg);
    color: var(--header-text-color);
    padding: 10px 20px;
    border-radius: 4px;
    flex-wrap: wrap; /* Allow wrapping for smaller screens */
}

.header h1 {
    flex: 1 1 auto;
    margin: 0;
}

.header h1 a {
    color: var(--header-text-color);
    text-decoration: none;
}

.header h1 a:hover {
    text-decoration: underline;
}

.theme-toggle {
    background: none;
    border: 1px solid var(--header-text-color);
    color: var(--header-text-color);
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    flex: 0 0 auto;
}

.theme-toggle:hover {
    background: var(--header-text-color);
    color: var(--header-bg);
}

.header form {
    flex: 0 0 auto;
    margin-left: 10px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(20em, 1fr));
    gap: 20px;
}

.card {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.card h2 {
    margin-top: 0;
    margin-bottom: 15px;
    font-size: 1.2em;
}

.data-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.data-list li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}

.data-list li:last-child {
    border-bottom: none;
}

.accordion-header {
    cursor: pointer;
    background: var(--card-bg);
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    margin-bottom: 5px;
    transition: background 0.3s;
}

.accordion-header:hover {
    background: var(--border-color);
}

.accordion-content {
    display: none;
    overflow: hidden;
}

.accordion-content.active {
    display: block;
}

.process-list-container {
    max-height: 400px;
    overflow-y: auto;
    border-radius: 4px;
    scrollbar-width: thin;
    scrollbar-color: var(--border-color) transparent;
}

.process-list-container::-webkit-scrollbar {
    width: 8px;
}

.process-list-container::-webkit-scrollbar-track {
    background: var(--card-bg);
    border-radius: 4px;
}

.process-list-container::-webkit-scrollbar-thumb {
    background-color: var(--border-color);
    border-radius: 4px;
}

.data-table {
    position: relative;
}

.data-table thead {
    position: sticky;
    top: 0;
    background-color: var(--card-bg);
    z-index: 1;
}

.data-table tbody tr:hover {
    background-color: var(--border-color);
}

.data-table th,
.data-table td {
    padding: 10px;
    text-align: left;
}

.process-card {
    grid-column: 1 / -1 !important;
}

.badge {
    background: var(--border-color);
    border-radius: 5px;
    font-size: medium;
    padding: 4px;
}

input[type="email"],
input[type="password"],
input[type="text"],
input[type="checkbox"],
button[type="submit"] {
    display: block;
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--card-bg);
    color: var(--text-color);
    font-size: 1em;
}

input[type="checkbox"] {
    width: auto;
    display: inline-block;
    margin-right: 10px;
}

button[type="submit"] {
    background: var(--border-color);
    cursor: pointer;
    transition: background 0.3s;
}

button[type="submit"]:hover {
    background: var(--text-color);
    color: var(--bg-color);
}

/* Global button style */
button {
    display: inline-block;
    padding: 10px 20px;
    font-size: 1em;
    font-family: inherit;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: var(--card-bg);
    color: var(--text-color);
    cursor: pointer;
    transition: background-color 0.3s, color 0.3s;
}

button:hover {
    background: var(--border-color);
    color: var(--bg-color);
}


/* Responsive adjustments */
@media (max-width: 768px) {
    .container {
        padding: 0 10px;
    }

    .header {
        flex-direction: column;
        align-items: flex-start;
    }

    .header h1 {
        margin-bottom: 10px;
    }

    .theme-toggle {
        width: 100%;
        margin-top: 10px;
    }

    .header form {
        width: 100%;
        margin-left: 0;
        margin-top: 10px;
    }
}

@media (max-width: 600px) {
    .header {
        text-align: center;
    }

    .header h1 a {
        font-size: 1.5em;
    }

    .theme-toggle {
        width: 100%;
        margin-top: 10px;
    }

    .header form {
        width: 100%;
        margin-left: 0;
        margin-top: 10px;
    }
}

    </style>
    <?php
}
?>