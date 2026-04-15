/*
  Requirement: Populate the single topic page and manage replies.
*/

// --- Global Data Store ---
let currentTopicId = null;
let currentReplies = [];

// --- Element Selections ---
const topicSubject = document.getElementById('topic-subject');
const opMessage = document.getElementById('op-message');
const opFooter = document.getElementById('op-footer');
const replyListContainer = document.getElementById('reply-list-container');
const replyForm = document.getElementById('reply-form');
const newReplyText = document.getElementById('new-reply');

// --- Functions ---

function getTopicIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get('id');
}

function renderOriginalPost(topic) {
  topicSubject.textContent = topic.subject;
  opMessage.textContent = topic.message;
  opFooter.textContent = "Posted by: " + topic.author + " on " + topic.created_at;
}

function createReplyArticle(reply) {
  const article = document.createElement('article');

  const p = document.createElement('p');
  p.textContent = reply.text;

  const footer = document.createElement('footer');
  footer.textContent = "Posted by: " + reply.author + " on " + reply.created_at;

  const div = document.createElement('div');
  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-reply-btn';
  deleteBtn.dataset.id = reply.id;
  deleteBtn.textContent = 'Delete';
  div.appendChild(deleteBtn);

  article.appendChild(p);
  article.appendChild(footer);
  article.appendChild(div);

  return article;
}

function renderReplies() {
  replyListContainer.innerHTML = '';
  currentReplies.forEach(reply => {
    replyListContainer.appendChild(createReplyArticle(reply));
  });
}

async function handleAddReply(event) {
  event.preventDefault();

  const replyText = newReplyText.value.trim();
  if (!replyText) return;

  const response = await fetch('./api/index.php?action=reply', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      topic_id: parseInt(currentTopicId),
      author: 'Student',
      text: replyText
    })
  });

  const result = await response.json();

  if (result.success === true) {
    currentReplies.push(result.data);
    renderReplies();
    newReplyText.value = '';
  }
}

async function handleReplyListClick(event) {
  if (event.target.classList.contains('delete-reply-btn')) {
    const id = parseInt(event.target.dataset.id);

    const response = await fetch(`./api/index.php?action=delete_reply&id=${id}`, {
      method: 'DELETE'
    });

    const result = await response.json();

    if (result.success === true) {
      currentReplies = currentReplies.filter(reply => reply.id !== id);
      renderReplies();
    }
  }
}

async function initializePage() {
  currentTopicId = getTopicIdFromURL();

  if (!currentTopicId) {
    topicSubject.textContent = 'Topic not found.';
    return;
  }

  const [topicRes, repliesRes] = await Promise.all([
    fetch(`./api/index.php?id=${currentTopicId}`),
    fetch(`./api/index.php?action=replies&topic_id=${currentTopicId}`)
  ]);

  const topicData = await topicRes.json();
  const repliesData = await repliesRes.json();

  currentReplies = (repliesData.success && Array.isArray(repliesData.data))
    ? repliesData.data
    : [];

  if (topicData.success === true) {
    renderOriginalPost(topicData.data);
    renderReplies();
    replyForm.addEventListener('submit', handleAddReply);
    replyListContainer.addEventListener('click', handleReplyListClick);
  } else {
    topicSubject.textContent = 'Topic not found.';
  }
}

// --- Initial Page Load ---
initializePage();
