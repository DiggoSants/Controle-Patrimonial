// ========== util ==========
const $ = (sel) => document.querySelector(sel);
const yearLeft = $('#yearLeft');
if (yearLeft) yearLeft.textContent = new Date().getFullYear();

const form = $('#loginForm');
const email = $('#email');
const password = $('#password');
const remember = $('#remember');
const submitBtn = $('#submitBtn');
const emailMsg = $('#emailMsg');
const passMsg = $('#passMsg');
const formOk = $('#formOk');
const formErr = $('#formErr');

const LOGIN_URL    = './FORMULARIO.php';
const REGISTER_URL = './FORMULARIO.php?action=register';

function show(el, text) { el.textContent = text; el.style.display = 'block'; }
function hide(el) { el.style.display = 'none'; }

// ========== LOGIN ==========
form.addEventListener('submit', async (e) => {
  e.preventDefault();
  hide(emailMsg); hide(passMsg); hide(formOk); hide(formErr);

  if (!email.value.trim()) { show(emailMsg, 'Informe seu e-mail.'); return; }
  if (!password.value.trim()) { show(passMsg, 'Informe sua senha.'); return; }
  if (password.value.length < 8) { show(passMsg, 'A senha deve ter ao menos 8 caracteres.'); return; }


  const payload = { 
    login: email.value.trim(),     
    senha: password.value,
    remember: !!remember.checked
  };

  submitBtn.disabled = true;
  const original = submitBtn.textContent;
  submitBtn.textContent = 'Entrando…';

  try {
    const res = await fetch(LOGIN_URL, {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      credentials: 'include',
      body: JSON.stringify(payload)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.ok) {
    throw new Error(data.message || 'Credenciais inválidas.');
  };

    show(formOk, 'Login realizado com sucesso!');
    const params = new URLSearchParams(location.search);
    const next = params.get('next') || '../dashboard.php';
    window.location.href = next;


  } catch (err) {
    show(formErr, err.message || 'Erro inesperado.');
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = original;
  }
});

// ========== REGISTRO ==========
const registerOverlay = $('#registerOverlay');
const registerClose   = $('#registerClose');
const registerForm    = $('#registerForm');
const registerOk      = $('#registerOk');
const registerErr     = $('#registerErr');
const registerSubmit  = $('#registerSubmit');
const regName         = $('#regName');
const regEmail        = $('#regEmail');
const regPass         = $('#regPass');
const regPass2        = $('#regPass2');

// abrir modal
$('#requestAccess').addEventListener('click', () => {
  registerOverlay.classList.add('open');
  registerOverlay.setAttribute('aria-hidden', 'false');
  regName.focus();
});

// fechar modal
registerClose.addEventListener('click', closeRegister);
registerOverlay.addEventListener('click', (e) => {
  if (e.target === registerOverlay) closeRegister();
});
function closeRegister() {
  registerOverlay.classList.remove('open');
  registerOverlay.setAttribute('aria-hidden', 'true');
  registerForm.reset();
  hide(registerOk); hide(registerErr);
  validateRegister();
}

// validação dinâmica
function validateRegister() {
  const nomeOk  = regName.value.trim().length > 0;
  const emailOk = /\S+@\S+\.\S+/.test(regEmail.value.trim());
  const passOk  = regPass.value.length >= 8;
  const matchOk = regPass.value === regPass2.value;

  registerSubmit.disabled = !(nomeOk && emailOk && passOk && matchOk);
}
[regName, regEmail, regPass, regPass2].forEach(el => el.addEventListener('input', validateRegister));

// envio do cadastro
registerForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  hide(registerOk); hide(registerErr);
  validateRegister();

  if (registerSubmit.disabled) {
    show(registerErr, 'Preencha todos os campos corretamente.');
    return;
  }

  const payload = {
    nome:  regName.value.trim(),
    email: regEmail.value.trim().toLowerCase(),
    senha: regPass.value
  };

  const original = registerSubmit.textContent;
  registerSubmit.disabled = true;
  registerSubmit.textContent = 'Criando…';

  try {
   const res = await fetch(REGISTER_URL, {
    method: 'POST',
    headers: { 'Content-Type':'application/json' },
    body: JSON.stringify(payload)
    });
    const data = await res.json().catch(()=> ({}));
    if (!res.ok) throw new Error(data.message || 'Falha ao registrar.');

    show(registerOk, 'Conta criada com sucesso! Agora você pode fazer login.');
    setTimeout(() => closeRegister(), 1200);
  } catch (err) {
    show(registerErr, err.message || 'Erro ao registrar.');
  } finally {
    registerSubmit.textContent = original;
    validateRegister();
  }
});
