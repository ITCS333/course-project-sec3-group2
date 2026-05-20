
const assignmentListSection = document.getElementById('assignment-list-section');


function createAssignmentArticle(assignment) {
  const article = document.createElement('article');

  const h2 = document.createElement('h2');
  h2.textContent = assignment.title;
  article.appendChild(h2);

  const dueP = document.createElement('p');
  dueP.textContent = `Due: ${assignment.due_date}`;
  article.appendChild(dueP);
  
  const descP = document.createElement('p');
  descP.textContent = assignment.description;
  article.appendChild(descP);

  const link = document.createElement('a');
  link.href = `details.html?id=${assignment.id}`;
  link.textContent = 'View Details & Discussion';
  article.appendChild(link);

  return article;
}


async function loadAssignments() {
  try {
    const response = await fetch('./api/index.php');
    const result = await response.json();

    assignmentListSection.innerHTML = '';

    if (result.success && Array.isArray(result.data)) {
      result.data.forEach(assignment => {
        const article = createAssignmentArticle(assignment);
        assignmentListSection.appendChild(article);
      });
    } else {
      assignmentListSection.textContent = 'No assignments found.';
    }
  } catch (err) {
    console.error('Error loading assignments:', err);
    assignmentListSection.textContent = 'Error loading assignments.';
  }
}

loadAssignments();
