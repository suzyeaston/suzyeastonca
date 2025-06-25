document.addEventListener('DOMContentLoaded', () => {
  const keys = ['C', 'D', 'E', 'F', 'G', 'A', 'B', 'C2'];
  const piano = document.getElementById('piano-container');

  if (!piano) {
    return;
  }

  keys.forEach((key) => {
    let pianoKey = document.createElement('div');
    pianoKey.className = 'piano-key';
    pianoKey.innerHTML = key;
    pianoKey.dataset.note = key;
    pianoKey.onclick = () => playNote(key);
    piano.appendChild(pianoKey);
  });

  function playNote(key) {
    let audio = new Audio(`.wav`);
    audio.play();
  }
});
