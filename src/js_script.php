<?php

function getThemeCode()
{
    ?>
    <script>
        function toggleTheme() {
            const theme = document.body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
        }

        if (document.body) {
            document.body.setAttribute('data-theme', localStorage.getItem('theme') || 'light');
        }
    </script>
    <?php
}

function getJavascriptCode()
{
    ?>
    <script>
        function toggleAccordion(event) {
            const content = event.target.nextElementSibling;
            content.classList.toggle('active');
        }

        function updateDashboard() {
            fetch('?api=1')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const dashboard = document.getElementById('dashboard');
                    dashboard.innerHTML = `
                        <div class="card">
                            <h2>System Information</h2>
                            <ul class="data-list">
                                ${Object.entries(data.basic).map(([k, v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                                <li><span>Uptime</span><span>${data.uptime}</span></li>
                            </ul>
                        </div>

                        <div class="card">
                            <h2>CPU Information</h2>
                            <ul class="data-list">
                                ${Object.entries(data.cpu).map(([k, v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                            </ul>
                        </div>

                        <div class="card">
                            <h2>Memory Usage</h2>
                            <ul class="data-list">
                                ${Object.entries(data.memory).map(([k, v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                            </ul>
                        </div>

                        <div class="card">
                            <h2>Load Average</h2>
                            <ul class="data-list">
                                ${Object.entries(data.load).map(([k, v]) => `
                                    <li><span>${k}</span><span>${v}</span></li>
                                `).join('')}
                            </ul>
                        </div>

                        <div class="card">
                            <h2>Disk Usage</h2>
                            ${Object.entries(data.disk).map(([mount, info]) => `
                                <div class="accordion-header" onclick="toggleAccordion(event)">${mount}</div>
                                <div class="accordion-content">
                                    <ul class="data-list">
                                        ${Object.entries(info).map(([k, v]) => `
                                            <li><span>${k}</span><span>${v}</span></li>
                                        `).join('')}
                                    </ul>
                                </div>
                            `).join('')}
                        </div>

                        <div class="card">
                            <h2>Network Interfaces</h2>
                            ${Object.entries(data.network).map(([iface, info]) => `
                                <div class="accordion-header" onclick="toggleAccordion(event)">${iface}</div>
                                <div class="accordion-content">
                                    <ul class="data-list">
                                        ${Object.entries(info).map(([k, v]) => `
                                            <li><span>${k}</span><span>${v}</span></li>
                                        `).join('')}
                                    </ul>
                                </div>
                            `).join('')}
                        </div>
                        <div class="card process-card">
                            <h2>Process List</h2>
                            <div class="process-list-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>PID</th>
                                            <th>CPU</th>
                                            <th>Memory</th>
                                            <th>Command</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${data.processes.map(process => `
                                            <tr>
                                                <td>${process.User}</td>
                                                <td>${process.PID}</td>
                                                <td>${process.CPU}%</td>
                                                <td>${process.Memory}%</td>
                                                <td>${process.Command}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                })
                .catch(error => console.error('Error updating dashboard:', error));
        }

        updateDashboard();
        setInterval(updateDashboard, 30000); // Update every 30 seconds
    </script>
    <?php
}
?>