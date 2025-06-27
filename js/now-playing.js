document.addEventListener('DOMContentLoaded', () => {
  const cont = document.getElementById('now-playing-container');
  if (!cont || typeof nowPlaying === 'undefined') return;

  const url = `https://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=${nowPlaying.username}&api_key=${nowPlaying.api_key}&format=json&limit=1`;

  fetch(url)
    .then(r => r.json())
    .then(data => {
      const track = data.recenttracks && data.recenttracks.track ? data.recenttracks.track[0] : null;
      if (!track) return;
      const img = track.image.pop()['#text'] || '';
      const artist = track.artist['#text'];
      const name = track.name;
      const html = `<div class="news-item"><img src="${img}" alt=""/><p>${artist} - ${name}</p></div>`;
      cont.innerHTML = html;
    })
    .catch(() => {});
});
