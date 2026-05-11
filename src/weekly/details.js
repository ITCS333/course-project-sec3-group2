let currentWeekId   = null;
let currentComments = [];

const weekTitle       = typeof document !== 'undefined' ? document.querySelector('#week-title')       : null;
const weekStartDate   = typeof document !== 'undefined' ? document.querySelector('#week-start-date')   : null;
const weekDescription = typeof document !== 'undefined' ? document.querySelector('#week-description')  : null;
const weekLinksList   = typeof document !== 'undefined' ? document.querySelector('#week-links-list')   : null;
const commentList     = typeof document !== 'undefined' ? document.querySelector('#comment-list')      : null;
const commentForm     = typeof document !== 'undefined' ? document.querySelector('#comment-form')      : null;
const newCommentInput = typeof document !== 'undefined' ? document.querySelector('#new-comment')       : null;

function getWeekIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

function renderWeekDetails(week) {
    weekTitle.textContent       = week.title;
    weekStartDate.textContent   = 'Starts on: ' + week.start_date;
    weekDescription.textContent = week.description;

    weekLinksList.innerHTML = '';
    
    (week.links || []).forEach(url => {
        const li = document.createElement('li');
        const a  = document.createElement('a');
        a.href        = url;
        a.textContent = url;
        li.appendChild(a);
        weekLinksList.appendChild(li);
    });
}

function createCommentArticle(comment) {
    const article = document.createElement('article');
    const p = document.createElement('p');
    p.textContent = comment.text;
    const footer = document.createElement('footer');
    footer.textContent = 'Posted by: ' + comment.author;
    article.appendChild(p);
    article.appendChild(footer);
    return article;
}

function renderComments() {
    commentList.innerHTML = '';
    currentComments.forEach(comment => {
        commentList.appendChild(createCommentArticle(comment));
    });
}

async function handleAddComment(event) {
    event.preventDefault();
    const commentText = newCommentInput.value.trim();
    if (!commentText) return;
    const response = await fetch('./api/index.php?action=comment', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({
            week_id: currentWeekId,
            author:  'Student',
            text:    commentText
        })
    });
    const result = await response.json();
    if (result.success) {
        currentComments.push(result.data);
        renderComments();
        newCommentInput.value = '';
    } else {
        console.error('Failed to post comment:', result.message);
    }
}

async function initializePage() {
    currentWeekId = getWeekIdFromURL();
    if (!currentWeekId) {
        weekTitle.textContent = 'Week not found.';
        return;
    }
    const [weekResponse, commentsResponse] = await Promise.all([
        fetch(`./api/index.php?id=${currentWeekId}`),
        fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
    ]);
    const weekResult     = await weekResponse.json();
    const commentsResult = await commentsResponse.json();
    currentComments = commentsResult.success ? commentsResult.data : [];
    if (weekResult.success) {
        renderWeekDetails(weekResult.data);
        renderComments();
        commentForm.addEventListener('submit', handleAddComment);
    } else {
        weekTitle.textContent = 'Week not found.';
    }
}

if (typeof module === 'undefined') {
    initializePage();
}

module.exports = {
    getWeekIdFromURL,
    renderWeekDetails,
    createCommentArticle,
    renderComments,
    handleAddComment,
    initializePage
};
