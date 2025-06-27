document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('now-listening-widget');
  if (!container || typeof nowPlaying === 'undefined') return;

  const url = `https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=${nowPlaying.username}&api_key=${nowPlaying.api_key}&format=json`;

  fetch(url)
    .then(res => res.json())
    .then(data => {
      const track = data.recenttracks.track[0];
      const title = track.name;
      const artist = track.artist['#text'];
      const image = track.image[2]['#text'];
      const playing = track['@attr'] && track['@attr'].nowplaying;

      container.innerHTML = `
        <div class="listening-inner fade-in">
          <p><strong>Now Listening:</strong></p>
          <img src="${image}" alt="album art" />
          <p>${title} — ${artist} ${playing ? '🎵 (Now Playing)' : ''}</p>
        </div>
      `;
    })
    .catch(() => {});
});
