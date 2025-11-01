<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TappedIn Radio Network - Live Players</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: white;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .subtitle {
            text-align: center;
            color: rgba(255,255,255,0.9);
            font-size: 1.2em;
            margin-bottom: 40px;
        }

        .player-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .player-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .player-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.4);
        }

        .player-container h2 {
            font-size: 1.8em;
            margin-bottom: 10px;
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .player-description {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .player-status {
            display: inline-block;
            padding: 5px 15px;
            background: #4CAF50;
            color: white;
            border-radius: 20px;
            font-size: 0.85em;
            margin-bottom: 15px;
        }

        .player-status.offline {
            background: #f44336;
        }

        iframe {
            width: 100%;
            min-height: 150px;
            border: none;
            border-radius: 8px;
            background: #f5f5f5;
        }

        .stream-links {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .stream-links h3 {
            font-size: 1em;
            color: #555;
            margin-bottom: 10px;
        }

        .stream-links a {
            display: inline-block;
            margin: 5px 5px 5px 0;
            padding: 8px 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9em;
            transition: background 0.3s ease;
        }

        .stream-links a:hover {
            background: #764ba2;
        }

        .info-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .info-section h2 {
            color: #333;
            margin-bottom: 15px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .info-section p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
            color: #555;
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .feature-list li:before {
            content: "✓ ";
            color: #4CAF50;
            font-weight: bold;
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }

            .player-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎵 TappedIn Radio Network</h1>
        <p class="subtitle">Professional Streaming Powered by AzuraCast</p>

        <div class="player-grid">
            <!-- Station 1: TappedIn Radio -->
            <div class="player-container">
                <h2>TappedIn Radio</h2>
                <p class="player-description">
                    Soundscapes, meditation music, and relaxing podcasts for mindfulness and peace.
                </p>
                <span class="player-status" id="status-tappedin">Checking...</span>
                <iframe src="https://azuracast.tappedin.fm/public/tappedin" id="player-tappedin"></iframe>
                <div class="stream-links">
                    <h3>Direct Stream Links:</h3>
                    <a href="https://azuracast.tappedin.fm/listen/tappedin/radio.mp3" target="_blank">MP3 Stream</a>
                    <a href="https://azuracast.tappedin.fm/api/nowplaying/tappedin" target="_blank">Now Playing</a>
                </div>
            </div>

            <!-- Station 2: Ray Wonder Mix -->
            <div class="player-container">
                <h2>Ray Wonder Music</h2>
                <p class="player-description">
                    Audio described content, diverse music collection, audiobooks, and storytelling.
                </p>
                <span class="player-status" id="status-raywonder">Checking...</span>
                <iframe src="https://radio.raywonderis.me/public/raywonder" id="player-raywonder"></iframe>
                <div class="stream-links">
                    <h3>Direct Stream Links:</h3>
                    <a href="https://radio.raywonderis.me/listen/raywonder/radio.mp3" target="_blank">MP3 Stream</a>
                    <a href="https://radio.raywonderis.me/api/nowplaying/raywonder" target="_blank">Now Playing</a>
                </div>
            </div>

            <!-- Station 3: Walterharper Music -->
            <div class="player-container">
                <h2>Walterharper Music</h2>
                <p class="player-description">
                    Curated music collection featuring various genres and styles.
                </p>
                <span class="player-status" id="status-walterharper">Checking...</span>
                <iframe src="https://stream.walterharper.com/public/walterharper" id="player-walterharper"></iframe>
                <div class="stream-links">
                    <h3>Direct Stream Links:</h3>
                    <a href="https://stream.walterharper.com/listen/walterharper/radio.mp3" target="_blank">MP3 Stream</a>
                    <a href="https://stream.walterharper.com/api/nowplaying/walterharper" target="_blank">Now Playing</a>
                </div>
            </div>

            <!-- Station 4: Devine Creations -->
            <div class="player-container">
                <h2>Devine Creations Channel</h2>
                <p class="player-description">
                    Specially curated music and content from Devine Creations.
                </p>
                <span class="player-status" id="status-devine">Checking...</span>
                <iframe src="https://azuracast.devine-creations.com/public/devine" id="player-devine"></iframe>
                <div class="stream-links">
                    <h3>Direct Stream Links:</h3>
                    <a href="https://azuracast.devine-creations.com/listen/devine/radio.mp3" target="_blank">MP3 Stream</a>
                    <a href="https://azuracast.devine-creations.com/api/nowplaying/devine" target="_blank">Now Playing</a>
                </div>
            </div>
        </div>

        <div class="info-section">
            <h2>About TappedIn Radio Network</h2>
            <p>
                TappedIn Radio Network is a collection of professionally managed streaming radio stations
                powered by AzuraCast. Each station offers unique content tailored to different audiences
                and preferences.
            </p>
            <h3 style="margin-top: 20px; color: #555;">Features:</h3>
            <ul class="feature-list">
                <li>24/7 automated streaming with professional AutoDJ</li>
                <li>High-quality audio streams (up to 320kbps)</li>
                <li>Mobile-friendly responsive players</li>
                <li>Now Playing information and metadata</li>
                <li>Song request capabilities (on select stations)</li>
                <li>Integration with FlexPBX for on-hold music</li>
                <li>Multiple stream quality options</li>
                <li>Accessible across all devices</li>
            </ul>
        </div>

        <div class="info-section">
            <h2>For Administrators</h2>
            <p>
                <strong>Admin Panel:</strong> <a href="https://azuracast.tappedin.fm/admin" style="color: #667eea;">AzuraCast Dashboard</a><br>
                <strong>Documentation:</strong> /home/flexpbxuser/documentation/AZURACAST_COMPLETE_SETUP_GUIDE.txt<br>
                <strong>Support:</strong> webmaster@raywonderis.me
            </p>
        </div>
    </div>

    <script>
        // Check station status via API
        const stations = [
            { id: 'tappedin', url: 'https://azuracast.tappedin.fm/api/nowplaying/tappedin' },
            { id: 'raywonder', url: 'https://radio.raywonderis.me/api/nowplaying/raywonder' },
            { id: 'walterharper', url: 'https://stream.walterharper.com/api/nowplaying/walterharper' },
            { id: 'devine', url: 'https://azuracast.devine-creations.com/api/nowplaying/devine' }
        ];

        stations.forEach(station => {
            fetch(station.url)
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('status-' + station.id);
                    if (data.station && data.station.is_online) {
                        statusEl.textContent = '🔴 Live';
                        statusEl.classList.remove('offline');
                    } else {
                        statusEl.textContent = '⚫ Offline';
                        statusEl.classList.add('offline');
                    }
                })
                .catch(error => {
                    const statusEl = document.getElementById('status-' + station.id);
                    statusEl.textContent = '⚠ Unknown';
                    statusEl.classList.add('offline');
                });
        });
    </script>
</body>
</html>
