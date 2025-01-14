<?php
getThemeCode();
function getCssStyle()
{
    ?>
    <style>
        :root {
            --bg-color: #ffffff;
            --text-color: #333333;
            --card-bg: #f5f5f5;
            --border-color: #dddddd;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #ffffff;
            --card-bg: #2d2d2d;
            --border-color: #404040;
        }
        * {
            transition: background-color var(--transition-speed) ease,
                color var(--transition-speed) ease,
                border-color var(--transition-speed) ease,
                box-shadow var(--transition-speed) ease;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .theme-toggle {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        @media (max-width: 600px) {
            .grid {
                grid-template-columns: 1fr;
            }
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

    </style>
    <?php
}
?>