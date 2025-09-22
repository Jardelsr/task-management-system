<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Task Management System API Documentation' }}</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@3.52.5/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@3.52.5/favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }

        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            margin:0;
            background: #fafafa;
        }

        .swagger-ui .topbar {
            background-color: #2c3e50;
        }

        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }

        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .custom-header h1 {
            margin: 0;
            font-size: 2.5em;
            font-weight: 300;
        }

        .custom-header p {
            margin: 10px 0 0 0;
            font-size: 1.2em;
            opacity: 0.9;
        }

        .api-info {
            background: white;
            padding: 20px;
            margin: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .api-info h3 {
            color: #2c3e50;
            margin-top: 0;
        }

        .api-info .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 4px solid #667eea;
        }

        .info-item strong {
            color: #2c3e50;
        }

        .endpoints-list {
            margin-top: 15px;
        }

        .endpoints-list a {
            display: inline-block;
            margin: 5px 10px 5px 0;
            padding: 5px 12px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .endpoints-list a:hover {
            background: #764ba2;
        }
    </style>
</head>

<body>
    <div class="custom-header">
        <h1>Task Management System API</h1>
        <p>Comprehensive RESTful API Documentation</p>
    </div>

    <div class="api-info">
        <h3>📋 API Information</h3>
        <div class="info-grid">
            <div class="info-item">
                <strong>Version:</strong> v1.0.0
            </div>
            <div class="info-item">
                <strong>Framework:</strong> Laravel Lumen {{ $lumenVersion ?? '11.0' }}
            </div>
            <div class="info-item">
                <strong>Environment:</strong> {{ $environment ?? 'production' }}
            </div>
            <div class="info-item">
                <strong>Base URL:</strong> {{ $baseUrl ?? request()->getSchemeAndHttpHost() }}/api/v1
            </div>
        </div>

        <h3>🔗 Quick Links</h3>
        <div class="endpoints-list">
            <a href="{{ request()->getSchemeAndHttpHost() }}/api/v1/openapi.json" target="_blank">OpenAPI Spec</a>
            <a href="{{ request()->getSchemeAndHttpHost() }}/api/v1/info" target="_blank">API Info</a>
            <a href="{{ request()->getSchemeAndHttpHost() }}/health" target="_blank">Health Check</a>
            <a href="{{ request()->getSchemeAndHttpHost() }}/api/v1/tasks" target="_blank">Tasks API</a>
            <a href="{{ request()->getSchemeAndHttpHost() }}/api/v1/logs" target="_blank">Logs API</a>
        </div>

        <h3>📖 Features</h3>
        <ul>
            <li><strong>Task Management:</strong> Full CRUD operations with soft deletes</li>
            <li><strong>Advanced Filtering:</strong> Filter by status, priority, assigned user, date ranges</li>
            <li><strong>Audit Logging:</strong> Comprehensive activity tracking with MongoDB</li>
            <li><strong>Pagination:</strong> Efficient pagination for large datasets</li>
            <li><strong>Validation:</strong> Input validation with detailed error responses</li>
            <li><strong>Rate Limiting:</strong> Built-in API rate limiting for security</li>
            <li><strong>Security:</strong> SQL injection protection and security headers</li>
        </ul>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@3.52.5/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            console.log('Initializing Swagger UI...');
            
            const specUrl = '{{ $specUrl ?? request()->getSchemeAndHttpHost() . "/api/v1/openapi.json" }}';
            console.log('Loading OpenAPI spec from:', specUrl);
            
            // First, test if we can fetch the spec directly
            fetch(specUrl)
                .then(response => {
                    console.log('OpenAPI fetch response:', response.status, response.statusText);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(spec => {
                    console.log('OpenAPI spec loaded successfully:', spec);
                    console.log('Paths found:', Object.keys(spec.paths || {}));
                    
                    // Initialize Swagger UI with the loaded spec
                    const ui = SwaggerUIBundle({
                        spec: spec, // Use the loaded spec directly instead of URL
                        dom_id: '#swagger-ui',
                        deepLinking: true,
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIStandalonePreset
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        layout: "StandaloneLayout",
                        validatorUrl: null,
                        docExpansion: "list",
                        operationsSorter: "alpha",
                        tagsSorter: "alpha",
                        filter: true,
                        supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                        onComplete: function() {
                            console.log('Swagger UI initialized successfully');
                        },
                        onFailure: function(data) {
                            console.error('Swagger UI initialization failed:', data);
                        }
                    });

                    window.ui = ui;
                })
                .catch(error => {
                    console.error('Failed to load OpenAPI spec:', error);
                    
                    // Show error message in the UI
                    document.getElementById('swagger-ui').innerHTML = `
                        <div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">
                            <h3>⚠️ Failed to Load API Documentation</h3>
                            <p><strong>Error:</strong> ${error.message}</p>
                            <p><strong>Spec URL:</strong> <a href="${specUrl}" target="_blank">${specUrl}</a></p>
                            <p>Please check:</p>
                            <ul>
                                <li>Docker containers are running</li>
                                <li>API server is accessible</li>
                                <li>OpenAPI specification is valid</li>
                            </ul>
                            <p><a href="${specUrl}" target="_blank">🔗 Try opening the OpenAPI spec directly</a></p>
                        </div>
                    `;
                });
        };
    </script>
</body>
</html>