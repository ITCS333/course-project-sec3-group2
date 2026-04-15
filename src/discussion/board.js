/*
  Requirement: Make the "Discussion Board" page interactive.
*/

// --- Global Data Store ---
let topics = [];

// --- Element Selections ---
const newTopicForm = document.getElementById('new-topic-form');
const topicListContainer = document.getElementById('topic-list-container');

// --- Functions ---

function createTopicArticle(topic) {
  const article = document.createElement('article');

  const h3 = document.createElement('h3');
  const a = document.createElement('a');
  a.href = `topic.html?id=${topic.id}`;
  a.textContent = topic.subject;
  h3.appendChild(a);

  const footer = document.createElement('footer');
  footer.textContent = `Posted by: ${topic.author} on ${topic.created_at}`;

  const div = document.createElement('div');

  const editBtn = document.createElement('button');
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = topic.id;
  editBtn.textContent = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = topic.id;
  deleteBtn.textContent = 'Delete';

  div.appendChild(editBtn);
  div.appendChild(deleteBtn);

  article.appendChild(h3);
  article.appendChild(footer);
  article.appendChild(div);

  return article;
}

function renderTopics() {
  topicListContainer.innerHTML = '';
  topics.forEach(topic => {
    topicListContainer.appendChild(createTopicArticle(topic));
  });
}

async function handleCreateTopic(event) {
  event.preventDefault();

  const submitBtn = document.getElementById('create-topic');
  const editId = submitBtn.dataset.editId;

  const subject = document.getElementById('topic-subject').value.trim();
  const message = document.getElementById('topic-message').value.trim();

  // If we're in edit mode, delegate to handleUpdateTopic
  if (editId) {
    await handleUpdateTopic(parseInt(editId), { subject, message });
    submitBtn.textContent = 'Create Topic';
    delete submitBtn.dataset.editId;
    newTopicForm.reset();
    return;
  }

  const response = await fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ subject, message, author: 'Student' })
  });

  const result = await response.json();

  if (result.success === true) {
    topics.push({
      id: result.id,
      subject,
      message,
      author: 'Student',
      created_at: result.created_at ?? new Date().toISOString().slice(0, 19).replace('T', ' ')
    });
    renderTopics();
    newTopicForm.reset();
  }
}

async function handleUpdateTopic(id, fields) {
  const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, subject: fields.subject, message: fields.message })
  });

  const result = await response.json();

  if (result.success === true) {
    topics = topics.map(topic =>
      topic.id === id
        ? { ...topic, subject: fields.subject, message: fields.message }
        : topic
    );
    renderTopics();
  }
}

async function handleTopicListClick(event) {
  // --- Delete ---
  if (event.target.classList.contains('delete-btn')) {
    const id = parseInt(event.target.dataset.id);

    const response = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    const result = await response.json();

    if (result.success === true) {
      topics = topics.filter(topic => topic.id !== id);
      renderTopics();
    }
  }

  // --- Edit ---
  if (event.target.classList.contains('edit-btn')) {
    const id = parseInt(event.target.dataset.id);
    const topic = topics.find(t => t.id === id);

    if (!topic) return;

    document.getElementById('topic-subject').value = topic.subject;
    document.getElementById('topic-message').value = topic.message;

    const submitBtn = document.getElementById('create-topic');
    submitBtn.textContent = 'Update Topic';
    submitBtn.dataset.editId = topic.id;
  }
}

async function loadAndInitialize() {
  const response = await fetch('./api/index.php');
  const result = await response.json();

  if (result.success === true) {
    topics = result.data;
  }

  renderTopics();
  newTopicForm.addEventListener('submit', handleCreateTopic);
  topicListContainer.addEventListener('click', handleTopicListClick);
}

// --- Initial Page Load ---
loadAndInitialize();
