const QUESTIONS = [
  {q:"UEFA Şampiyonlar Ligi kupasını en çok kazanan kulüp hangisidir?", a:["Real Madrid","Milan","Liverpool","Bayern Münih"], c:0},
  {q:"2010 Ballon d'Or ödülünü hangi oyuncu kazanmıştır?", a:["Cristiano Ronaldo","Andres Iniesta","Lionel Messi","Xavi"], c:2},
  {q:"Türkiye'nin EURO 2008'de ulaştığı aşama hangisidir?", a:["Çeyrek final","Yarı final","Final","Son 16"], c:1},
  {q:"Bir futbol takımında sahada aynı anda kaç oyuncu bulunur?", a:["9","10","11","12"], c:2},
  {q:"Penaltı noktası kale çizgisinden yaklaşık kaç metre uzaktadır?", a:["9 m","11 m","13 m","15 m"], c:1},
  {q:"UEFA Avrupa Şampiyonası genel olarak kaç yılda bir düzenlenir?", a:["2","3","4","5"], c:2}
];

const ATTACKERS = [
  {x:15.8, y:73.5, label:"Başlangıç"},
  {x:31.2, y:53.2, label:"Stoper"},
  {x:49.6, y:66.4, label:"Orta saha"},
  {x:68.6, y:46.1, label:"10 Numara"},
  {x:81.6, y:58.2, label:"Forvet"}
];

const OPPONENTS = [
  {x:36.5, y:33.5},
  {x:45.2, y:50.4},
  {x:57.8, y:38.6},
  {x:63.5, y:63.6},
  {x:74.6, y:43.5},
  {x:76.5, y:69.2}
];

const KEEPER_POS = {x:94.2, y:53.3};
const SHOT_TARGET = {x:96.1, y:43.8};
const BALL_OFFSET = {x:2.35, y:-1.15};
const PITCH_RATIO = 1672 / 941;
const runFrames = [...Array(6)].map((_,i)=>`assets/player/run_${String(i+1).padStart(2,'0')}.png`);
const ballFrames = [...Array(6)].map((_,i)=>`assets/ball/ball_${String(i+1).padStart(2,'0')}.png`);
[...runFrames, ...ballFrames].forEach(src=>{ const im = new Image(); im.src = src; });

const splash = document.getElementById('splash');
const game = document.getElementById('game');
const startBtn = document.getElementById('startBtn');
const howBtn = document.getElementById('howBtn');
const howModal = document.getElementById('howModal');
const closeHow = document.getElementById('closeHow');
const modalPlay = document.getElementById('modalPlay');
const fullBtn = document.getElementById('fullBtn');
const soundBtn = document.getElementById('soundBtn');
const result = document.getElementById('result');
const againBtn = document.getElementById('againBtn');

const pitch = document.getElementById('pitch');
const pitchWrap = document.querySelector('.pitch-wrap');
const playersEl = document.getElementById('players');
const opponentsEl = document.getElementById('opponents');
const passPath = document.getElementById('passPath');
const ball = document.getElementById('ball');
const questionEl = document.getElementById('question');
const answersEl = document.getElementById('answers');
const timerEl = document.getElementById('timer');
const questionNo = document.getElementById('questionNo');
const correctHud = document.getElementById('correctHud');
const progressEl = document.getElementById('progress');
const goalNeed = document.getElementById('goalNeed');
const statusToast = document.getElementById('statusToast');
const goalFlash = document.getElementById('goalFlash');
const keeper = document.getElementById('keeper');
const activePointer = document.getElementById('activePointer');

let audioCtx = null;
let soundOn = true;
let qIndex = 0;
let correct = 0;
let wrong = 0;
let score = 0;
let seconds = 10;
let timerId = null;
let locked = false;
let activePlayer = 0;
let playerFrame = 0;
let ballFrame = 0;

function tone(freq=600, duration=.08, type='sine', vol=.035){
  if(!soundOn) return;
  try{
    audioCtx ||= new (window.AudioContext || window.webkitAudioContext)();
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = type; osc.frequency.value = freq; gain.gain.value = vol;
    osc.connect(gain); gain.connect(audioCtx.destination);
    osc.start();
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + duration);
    osc.stop(audioCtx.currentTime + duration);
  }catch(err){}
}
const successSound = ()=>{ tone(760,.08); setTimeout(()=>tone(980,.12),75); };
const failSound = ()=> tone(190,.16,'sawtooth',.025);
const goalSound = ()=> [392,523,659,784].forEach((f,i)=>setTimeout(()=>tone(f,.22,'square',.03), i*95));

function showGame(){
  splash.classList.remove('active');
  game.classList.add('active');
  howModal.classList.remove('show');
  syncPitchSize();
  startRound();
}
startBtn.onclick = showGame;
modalPlay.onclick = showGame;
howBtn.onclick = ()=> howModal.classList.add('show');
closeHow.onclick = ()=> howModal.classList.remove('show');
againBtn.onclick = ()=> { result.classList.remove('show'); startRound(); };
soundBtn.onclick = ()=>{ soundOn = !soundOn; soundBtn.textContent = soundOn ? '🔊' : '🔇'; };

function getFullscreenElement(){
  return document.fullscreenElement || document.webkitFullscreenElement || null;
}

function updateFullscreenButton(){
  const expanded = Boolean(getFullscreenElement());
  fullBtn.classList.toggle('is-fullscreen', expanded);
  fullBtn.setAttribute('aria-pressed', String(expanded));
  fullBtn.setAttribute('aria-label', expanded ? 'Tam ekrandan çık' : 'Tam ekrana geç');
  fullBtn.title = expanded ? 'Tam ekrandan çık' : 'Tam ekrana geç';
}

async function toggleFullscreen(){
  try{
    if(!getFullscreenElement()){
      const request = document.documentElement.requestFullscreen || document.documentElement.webkitRequestFullscreen;
      if(!request) return;
      await request.call(document.documentElement);
      try{ await screen.orientation?.lock?.('landscape'); }catch(err){}
    }else{
      const exit = document.exitFullscreen || document.webkitExitFullscreen;
      await exit?.call(document);
      try{ screen.orientation?.unlock?.(); }catch(err){}
    }
  }catch(err){}
  updateFullscreenButton();
}

fullBtn.onclick = toggleFullscreen;
document.addEventListener('fullscreenchange', updateFullscreenButton);
document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
updateFullscreenButton();

function syncPitchSize(){
  const availableWidth = pitchWrap.clientWidth;
  const availableHeight = pitchWrap.clientHeight;
  if(!availableWidth || !availableHeight) return;

  let width = availableWidth;
  let height = width / PITCH_RATIO;
  if(height > availableHeight){
    height = availableHeight;
    width = height * PITCH_RATIO;
  }

  pitch.style.width = `${width}px`;
  pitch.style.height = `${height}px`;
}

const pitchResizeObserver = new ResizeObserver(()=>{
  syncPitchSize();
  if(game.classList.contains('active') && !locked){
    const current = ATTACKERS[activePlayer];
    if(current) placeBallAt(current.x, current.y);
  }
});
pitchResizeObserver.observe(pitchWrap);

function renderProgress(){
  progressEl.innerHTML = '';
  for(let i=0;i<6;i++){
    const pip = document.createElement('span');
    pip.className = 'pip';
    if(i < qIndex) pip.classList.add(QUESTIONS[i]._ok ? 'correct' : 'wrong');
    if(i === qIndex && qIndex < 6) pip.classList.add('active');
    progressEl.appendChild(pip);
  }
  correctHud.textContent = correct;
  goalNeed.textContent = `${Math.max(0, 4 - correct)} doğru daha = GOL`;
}

function createPlayers(){
  playersEl.innerHTML = '';
  ATTACKERS.forEach((pos, idx)=>{
    const div = document.createElement('div');
    div.className = 'player';
    div.dataset.index = idx;
    div.style.left = `${pos.x}%`;
    div.style.top = `${pos.y}%`;

    const img = document.createElement('img');
    img.src = runFrames[(idx + playerFrame) % runFrames.length];
    img.alt = pos.label;

    const ring = document.createElement('span');
    ring.className = 'base-ring';
    div.appendChild(ring);
    div.appendChild(img);
    playersEl.appendChild(div);
  });
  updatePlayerStates();
}

function createOpponents(){
  opponentsEl.innerHTML = '';
  OPPONENTS.forEach((pos, idx)=>{
    const div = document.createElement('div');
    div.className = 'opponent';
    div.style.left = `${pos.x}%`;
    div.style.top = `${pos.y}%`;
    const img = document.createElement('img');
    img.src = runFrames[idx % runFrames.length];
    img.alt = 'Rakip oyuncu';
    div.appendChild(img);
    opponentsEl.appendChild(div);
  });
}

function updateAnimatedFrames(){
  if(document.hidden || !game.classList.contains('active')) return;
  playerFrame = (playerFrame + 1) % runFrames.length;
  ballFrame = (ballFrame + 1) % ballFrames.length;
  playersEl.querySelectorAll('img').forEach((img, idx)=> img.src = runFrames[(playerFrame + idx) % runFrames.length]);
  opponentsEl.querySelectorAll('img').forEach((img, idx)=> img.src = runFrames[(playerFrame + idx + 2) % runFrames.length]);
  ball.src = ballFrames[ballFrame];
}
setInterval(updateAnimatedFrames, 120);

function updatePlayerStates(){
  [...playersEl.children].forEach((el, idx)=>{
    el.classList.toggle('active', idx === activePlayer);
    el.classList.toggle('done', idx < activePlayer);
  });
  const p = ATTACKERS[activePlayer];
  activePointer.style.left = `${p.x}%`;
  activePointer.style.top = `${p.y}%`;
  placeBallAt(p.x, p.y);
}

function placeBallAt(px, py){
  setBallTransform(px + BALL_OFFSET.x, py + BALL_OFFSET.y);
}

function setBallTransform(px, py, scale=1, rotation=0, width=pitch.clientWidth, height=pitch.clientHeight){
  const x = width * px / 100;
  const y = height * py / 100;
  ball.style.transform = `translate3d(${x}px,${y}px,0) translate(-50%,-50%) scale(${scale}) rotate(${rotation}deg)`;
}

function setKeeper(){
  keeper.style.left = `${KEEPER_POS.x}%`;
  keeper.style.top = `${KEEPER_POS.y}%`;
  keeper.style.transform = '';
}

function startRound(){
  qIndex = 0;
  correct = 0;
  wrong = 0;
  score = 0;
  seconds = 10;
  locked = false;
  activePlayer = 0;
  QUESTIONS.forEach(q => delete q._ok);
  createPlayers();
  createOpponents();
  setKeeper();
  renderProgress();
  loadQuestion();
}

function loadQuestion(){
  if(correct >= 4){ shootGoal(); return; }
  if(qIndex >= 6){ finish(false); return; }

  locked = false;
  seconds = 10;
  timerEl.textContent = seconds;
  setTimerVisual();
  questionNo.textContent = `SORU ${qIndex + 1} / 6`;

  const item = QUESTIONS[qIndex];
  questionEl.textContent = item.q;
  answersEl.innerHTML = '';

  item.a.forEach((text, idx)=>{
    const btn = document.createElement('button');
    btn.className = 'answer';
    btn.innerHTML = `<b>${String.fromCharCode(65 + idx)}</b> ${text}`;
    btn.onclick = ()=> choose(idx, btn);
    answersEl.appendChild(btn);
  });

  clearInterval(timerId);
  timerId = setInterval(()=>{
    seconds -= 1;
    timerEl.textContent = seconds;
    setTimerVisual();
    if(seconds <= 0){
      clearInterval(timerId);
      timeoutAnswer();
    }
  }, 1000);

  renderProgress();
}

function setTimerVisual(){
  document.querySelector('.timer-ring').style.setProperty('--pct', seconds * 10);
  timerEl.style.color = seconds <= 3 ? '#ff6f7f' : '#ffffff';
}

function disableAnswers(){
  [...answersEl.children].forEach(btn => btn.disabled = true);
}

function choose(selectedIndex, button){
  if(locked) return;
  locked = true;
  clearInterval(timerId);
  disableAnswers();

  const item = QUESTIONS[qIndex];
  const ok = selectedIndex === item.c;
  item._ok = ok;

  const rightButton = [...answersEl.children][item.c];
  rightButton?.classList.add('correct');

  if(ok){
    correct += 1;
    score += 100 + seconds * 10;
    successSound();
    toast('DOĞRU! Pas başarılı');
    const from = activePlayer;
    const to = Math.min(activePlayer + 1, ATTACKERS.length - 1);
    animatePass(from, to, ()=>{
      activePlayer = to;
      updatePlayerStates();
      advance();
    });
  } else {
    wrong += 1;
    button.classList.add('wrong');
    failSound();
    toast('HAMLE BOZULDU');
    setTimeout(advance, 820);
  }

  renderProgress();
}

function timeoutAnswer(){
  if(locked) return;
  locked = true;
  wrong += 1;
  disableAnswers();
  QUESTIONS[qIndex]._ok = false;
  [...answersEl.children][QUESTIONS[qIndex].c]?.classList.add('correct');
  failSound();
  toast('SÜRE DOLDU');
  renderProgress();
  setTimeout(advance, 850);
}

function advance(){
  qIndex += 1;
  setTimeout(loadQuestion, 280);
}

function toast(message){
  statusToast.textContent = message;
  statusToast.classList.add('show');
  clearTimeout(toast._timer);
  toast._timer = setTimeout(()=> statusToast.classList.remove('show'), 900);
}

function percToViewBox({x, y}){
  return { x: x * 10, y: y * 5.6 };
}

function animatePass(fromIndex, toIndex, done){
  const start = ATTACKERS[fromIndex];
  const end = ATTACKERS[toIndex];
  const startBall = {x: start.x + BALL_OFFSET.x, y: start.y + BALL_OFFSET.y};
  const endBall = {x: end.x + BALL_OFFSET.x, y: end.y + BALL_OFFSET.y};
  const p1 = percToViewBox(startBall);
  const p2 = percToViewBox(endBall);
  const cx = (p1.x + p2.x) / 2;
  const cy = Math.min(p1.y, p2.y) - 60;

  passPath.setAttribute('d', `M ${p1.x} ${p1.y} Q ${cx} ${cy} ${p2.x} ${p2.y}`);
  passPath.classList.remove('go');
  void passPath.getBoundingClientRect();
  passPath.classList.add('go');

  const startTime = performance.now();
  const duration = 720;
  const pitchWidth = pitch.clientWidth;
  const pitchHeight = pitch.clientHeight;

  function step(now){
    const t = Math.min(1, (now - startTime) / duration);
    const ease = 1 - Math.pow(1 - t, 3);
    const x = startBall.x + (endBall.x - startBall.x) * ease;
    const y = startBall.y + (endBall.y - startBall.y) * ease - Math.sin(Math.PI * ease) * 5.2;
    setBallTransform(x, y, 1, 0, pitchWidth, pitchHeight);
    if(t < 1) requestAnimationFrame(step);
    else done?.();
  }
  requestAnimationFrame(step);
}

function shootGoal(){
  locked = true;
  clearInterval(timerId);
  goalSound();
  toast('ŞUT!');

  const start = ATTACKERS[activePlayer];
  const startBall = {x: start.x + BALL_OFFSET.x, y: start.y + BALL_OFFSET.y};
  const p1 = percToViewBox(startBall);
  const p2 = percToViewBox(SHOT_TARGET);
  const cx = p1.x + (p2.x - p1.x) * 0.62;
  const cy = Math.min(p1.y, p2.y) - 110;
  passPath.setAttribute('d', `M ${p1.x} ${p1.y} Q ${cx} ${cy} ${p2.x} ${p2.y}`);
  passPath.classList.remove('go');
  void passPath.getBoundingClientRect();
  passPath.classList.add('go');

  const startTime = performance.now();
  const duration = 860;
  const pitchWidth = pitch.clientWidth;
  const pitchHeight = pitch.clientHeight;
  function step(now){
    const t = Math.min(1, (now - startTime) / duration);
    const e = t * t * (3 - 2 * t);
    const x = startBall.x + (SHOT_TARGET.x - startBall.x) * e;
    const y = startBall.y + (SHOT_TARGET.y - startBall.y) * e - Math.sin(Math.PI * e) * 9;
    setBallTransform(x, y, 1 - 0.22 * e, 720 * e, pitchWidth, pitchHeight);
    keeper.style.transform = `translate(-50%,-100%) translateX(${-12 * e}px) scale(${1 + (e * 0.06)})`;

    if(t < 1){
      requestAnimationFrame(step);
    } else {
      goalFlash.classList.remove('show');
      void goalFlash.offsetWidth;
      goalFlash.classList.add('show');
      setTimeout(()=> finish(true), 980);
    }
  }
  requestAnimationFrame(step);
}

function finish(goal){
  const bonus = goal ? 600 : 0;
  score += bonus;
  document.getElementById('resultIcon').textContent = goal ? '⚽' : '⏱️';
  document.getElementById('resultTitle').textContent = goal ? 'GOOOL!' : 'ATAK BİTTİ';
  document.getElementById('resultText').textContent = goal
    ? `Harika! ${correct} doğruyla hücumu gole çevirdin.`
    : `6 soruda ${correct} doğru yaptın. Gol için en az 4 doğru gerekiyordu.`;
  document.getElementById('resCorrect').textContent = correct;
  document.getElementById('resWrong').textContent = wrong;
  document.getElementById('resScore').textContent = goal ? score : 0;
  result.classList.add('show');
}

document.addEventListener('keydown', (e)=>{
  if(!game.classList.contains('active') || locked) return;
  const map = { a:0, b:1, c:2, d:3, '1':0, '2':1, '3':2, '4':3 };
  const idx = map[e.key.toLowerCase()];
  if(idx !== undefined) answersEl.children[idx]?.click();
});
