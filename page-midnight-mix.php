<?php
/*
Template Name: Midnight Mix (Retro Snake)
Description: A retro Snake game embedded right in your Midnight Mix page.
*/

get_header();
?>
<main id="main-content" class="midnight-snake-page">
  <header id="retro-game-header">
    <div id="stacked-nerd-title">🕹️ Midnight Arcade 🕹️</div>
  </header>

  <section class="game-container">
    <canvas id="snake-game" width="400" height="400"></canvas>
    <p class="game-instructions">
      Use your arrow keys to guide the snake. Eat the red apples to grow. Don’t hit the walls!
    </p>
  </section>
</main>

<script>
(function(){
  const canvas = document.getElementById('snake-game');
  const ctx    = canvas.getContext('2d');
  const scale  = 20;
  const rows   = canvas.height / scale;
  const cols   = canvas.width  / scale;

  let snake = [{ x:10, y:10 }];
  let dir   = { x: 1, y: 0 };
  let food  = randomFood();

  function randomFood(){
    return {
      x: Math.floor(Math.random() * cols),
      y: Math.floor(Math.random() * rows)
    };
  }

  window.addEventListener('keydown', e => {
    switch(e.key){
      case 'ArrowUp':    if (dir.y !== 1) dir = { x: 0, y:-1 }; break;
      case 'ArrowDown':  if (dir.y !==-1) dir = { x: 0, y: 1 }; break;
      case 'ArrowLeft':  if (dir.x !== 1) dir = { x:-1, y: 0 }; break;
      case 'ArrowRight': if (dir.x !==-1) dir = { x: 1, y: 0 }; break;
    }
  });

  function update(){
    const head = {
      x: (snake[0].x + dir.x + cols) % cols,
      y: (snake[0].y + dir.y + rows) % rows
    };
    snake.unshift(head);

    if (head.x === food.x && head.y === food.y) {
      food = randomFood();
    } else {
      snake.pop();
    }
  }

  function draw(){
    // black background
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // snake (neon green)
    ctx.fillStyle = '#0f0';
    snake.forEach(seg =>
      ctx.fillRect(seg.x * scale, seg.y * scale, scale, scale)
    );

    // food (neon red)
    ctx.fillStyle = '#f00';
    ctx.fillRect(food.x * scale, food.y * scale, scale, scale);
  }

  setInterval(() => {
    update();
    draw();
  }, 100);
})();
</script>

<?php get_footer(); ?>
