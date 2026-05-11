let weeks = [];

const weekForm  = document.querySelector('#week-form');
const weekTbody = document.querySelector('#weeks-tbody');

function createWeekRow(week) {
    const tr = document.createElement('tr');

    const tdTitle = document.createElement('td');
    tdTitle.textContent = week.title;

    const tdDate = document.createElement('td');
    tdDate.textContent = week.start_date;

    const tdDesc = document.createElement('td');
    tdDesc.textContent = week.description;

    const tdActions = document.createElement('td');

    const editBtn = document.createElement('button');
    editBtn.textContent  = 'Edit';
    editBtn.className    = 'edit-btn';
    editBtn.dataset.id   = week.id;

    const deleteBtn = document.createElement('button');
    deleteBtn.textContent = 'Delete';
    deleteBtn.className   = 'delete-btn';
    deleteBtn.dataset.id  = week.id;

    tdActions.appendChild(editBtn);
    tdActions.appendChild(deleteBtn);

    tr.appendChild(tdTitle);
    tr.appendChild(tdDate);
    tr.appendChild(tdDesc);
    tr.appendChild(tdActions);

    return tr;
}

function renderTable() {
    weekTbody.innerHTML = '';
    weeks.forEach(week => {
        weekTbody.appendChild(createWeekRow(week));
    });
}

async function handleAddWeek(event) {
    event.preventDefault();

    const title       = document.querySelector('#week-title').value.trim();
    const start_date  = document.querySelector('#week-start-date').value.trim();
    const description = document.querySelector('#week-description').value.trim();
    const links       = document.querySelector('#week-links').value
                            .split('\n')
                            .map(l => l.trim())
                            .filter(l => l !== '');

    const submitBtn = document.querySelector('#add-week');
    const editId    = submitBtn.dataset.editId;

    if (editId) {
        await handleUpdateWeek(Number(editId), { title, start_date, description, links });
        return;
    }

    const response = await fetch('./api/index.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ title, start_date, description, links })
    });

    const result = await response.json();

    if (result.success) {
        weeks.push({ id: result.id, title, start_date, description, links });
        renderTable();
        weekForm.reset();
    } else {
        console.error('Failed to create week:', result.message);
    }
}

async function handleUpdateWeek(id, fields) {
    const response = await fetch('./api/index.php', {
        method:  'PUT',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id, ...fields })
    });

    const result = await response.json();

    if (result.success) {
        weeks = weeks.map(w => w.id === id ? { id, ...fields } : w);
        renderTable();
        weekForm.reset();

        const submitBtn = document.querySelector('#add-week');
        submitBtn.textContent = 'Add Week';
        delete submitBtn.dataset.editId;
    } else {
        console.error('Failed to update week:', result.message);
    }
}

async function handleTableClick(event) {
    const target = event.target;
    const id     = Number(target.dataset.id);

    if (target.classList.contains('delete-btn')) {
        const response = await fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE'
        });

        const result = await response.json();

        if (result.success) {
            weeks = weeks.filter(w => w.id !== id);
            renderTable();
        } else {
            console.error('Failed to delete week:', result.message);
        }
    }

    if (target.classList.contains('edit-btn')) {
        const week = weeks.find(w => w.id === id);
        if (!week) return;

        document.querySelector('#week-title').value       = week.title;
        document.querySelector('#week-start-date').value  = week.start_date;
        document.querySelector('#week-description').value = week.description;
        document.querySelector('#week-links').value       = week.links.join('\n');

        const submitBtn = document.querySelector('#add-week');
        submitBtn.textContent    = 'Update Week';
        submitBtn.dataset.editId = week.id;
    }
}

async function loadAndInitialize() {
    const response = await fetch('./api/index.php');
    const result   = await response.json();

    if (result.success) {
        weeks = result.data;
        renderTable();
    } else {
        console.error('Failed to load weeks:', result.message);
    }

    weekForm.addEventListener('submit', handleAddWeek);
    weekTbody.addEventListener('click', handleTableClick);
}

loadAndInitialize();
