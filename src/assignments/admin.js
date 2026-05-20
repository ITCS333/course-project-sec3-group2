
let assignments = [];

const assignmentForm = document.getElementById('assignment-form');
const assignmentsTbody = document.getElementById('assignments-tbody');
const submitBtn = document.getElementById('add-assignment');

function createAssignmentRow(assignment) {
 const tr = document.createElement('tr');

  const tdTitle = document.createElement('td');
  tdTitle.textContent = assignment.title;
  tr.appendChild(tdTitle);


  const tdDueDate = document.createElement('td');
  tdDueDate.textContent = assignment.due_date;
  tr.appendChild(tdDueDate);

  
  const tdDescription = document.createElement('td');
  tdDescription.textContent = assignment.description;
  tr.appendChild(tdDescription);

 
  const tdActions = document.createElement('td');
  const editBtn = document.createElement('button');
  editBtn.className = 'edit-btn';
  editBtn.dataset.id = assignment.id;
  editBtn.textContent = 'Edit';

  const deleteBtn = document.createElement('button');
  deleteBtn.className = 'delete-btn';
  deleteBtn.dataset.id = assignment.id;
  deleteBtn.textContent = 'Delete';

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);
  tr.appendChild(tdActions);

  return tr;
}


function renderTable() {
 assignmentsTbody.innerHTML = '';
  assignments.forEach(a => {
    const row = createAssignmentRow(a);
    assignmentsTbody.appendChild(row);
  });
}


async function handleAddAssignment(event) {
   event.preventDefault();

  const title = document.getElementById('assignment-title').value.trim();
  const due_date = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value.trim();
  const filesRaw = document.getElementById('assignment-files').value.trim();
  const files = filesRaw ? filesRaw.split('\n').map(f => f.trim()).filter(f => f) : [];

  const editId = submitBtn.dataset.editId;

  if (editId) {
    await handleUpdateAssignment(parseInt(editId, 10), { title, due_date, description, files });
  } else {
    const response = await fetch('./api/index.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, due_date, description, files })
    });
    const result = await response.json();
    if (result.success) {
      assignments.push({ id: result.id, title, due_date, description, files });
      renderTable();
      assignmentForm.reset();
    }
  }
}


async function handleUpdateAssignment(id, fields) {
 const response = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...fields })
  });
  const result = await response.json();
  if (result.success) {
    const index = assignments.findIndex(a => a.id === id);
    if (index !== -1) {
      assignments[index] = { id, ...fields };
    }
    renderTable();
    assignmentForm.reset();
    submitBtn.textContent = 'Add Assignment';
    delete submitBtn.dataset.editId;
  }
}


async function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = parseInt(target.dataset.id, 10);
    const response = await fetch(`./api/index.php?id=${id}`, { method: 'DELETE' });
    const result = await response.json();
    if (result.success) {
      assignments = assignments.filter(a => a.id !== id);
      renderTable();
    }
  }

  if (target.classList.contains('edit-btn')) {
    const id = parseInt(target.dataset.id, 10);
    const assignment = assignments.find(a => a.id === id);
    if (assignment) {
      document.getElementById('assignment-title').value = assignment.title;
      document.getElementById('assignment-due-date').value = assignment.due_date;
      document.getElementById('assignment-description').value = assignment.description;
      document.getElementById('assignment-files').value = assignment.files.join('\n');

      submitBtn.textContent = 'Update Assignment';
      submitBtn.dataset.editId = assignment.id;
    }
  }
}


async function loadAndInitialize() {
   const response = await fetch('./api/index.php');
  const result = await response.json();
  if (result.success) {
    assignments = result.data;
    renderTable();
  }
  assignmentForm.addEventListener('submit', handleAddAssignment);
  assignmentsTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();
