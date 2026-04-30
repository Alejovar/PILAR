// AUN NO FUNCIONAL, ES EL JS PARA LA INTERFAZ PRINCIPAL DE LOS MESEROS, DISPONIBLE EN UN FUTURO CERCANO
document.addEventListener('DOMContentLoaded', () => {
    // Get references to DOM elements
    const addTableButton = document.getElementById('addTableButton');
    const createTableModal = document.getElementById('createTableModal');
    const closeButton = createTableModal.querySelector('.close-button');
    const createTableForm = document.getElementById('createTableForm');
    const tableGridContainer = document.getElementById('tableGridContainer');

    const tableNumberInput = document.getElementById('tableNumberInput');
    const numPeopleInput = document.getElementById('numPeopleInput');

    // Set to store active table numbers for uniqueness check
    // In a real application, this would be managed server-side (e.g., PHP with a database).
    let activeTableNumbers = new Set();

    // Function to show the modal
    addTableButton.addEventListener('click', () => {
        createTableModal.style.display = 'flex'; // Use flex to center the modal
    });

    // Function to close the modal by clicking the 'x'
    closeButton.addEventListener('click', () => {
        createTableModal.style.display = 'none';
        createTableForm.reset(); // Clear the form on close
    });

    // Function to close the modal by clicking outside of it
    window.addEventListener('click', (event) => {
        if (event.target === createTableModal) {
            createTableModal.style.display = 'none';
            createTableForm.reset(); // Clear the form on close
        }
    });

    // Function to create a table card HTML element
    function createTableCard(tableNumber, numPeople) {
        const tableCard = document.createElement('div');
        tableCard.classList.add('table-card');
        tableCard.innerHTML = `
            <i class="fa-solid fa-user-group"></i>
            <span class="table-number">${tableNumber}</span>
            <span class="table-people">${numPeople} personas</span> 
        `;
        // Store table number as a data attribute for easy access (e.g., for removal)
        tableCard.dataset.tableNumber = tableNumber;
        return tableCard;
    }

    // Handle form submission to create a new table
    createTableForm.addEventListener('submit', (event) => {
        event.preventDefault(); // Prevent default form submission

        const tableNumber = parseInt(tableNumberInput.value);
        const numPeople = parseInt(numPeopleInput.value);

        // Basic validation for input numbers
        if (isNaN(tableNumber) || isNaN(numPeople) || tableNumber <= 0 || numPeople <= 0) {
            window.appAlert('Por favor, ingresa números válidos para la mesa y el número de personas.');
            return;
        }

        // Validate uniqueness: Check if table number is already active
        if (activeTableNumbers.has(tableNumber)) {
            window.appAlert(`Error: La mesa número ${tableNumber} ya está en uso. Por favor, elige otro número.`);
            return;
        }

        // --- At this point, in a real application, you would send this data to your backend (PHP) ---
        // Example:
        // fetch('/api/create-table', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify({ tableNumber: tableNumber, numPeople: numPeople, waiterId: 'currentWaiter' })
        // })
        // .then(response => response.json())
        // .then(data => {
        //     if (data.success) {
        //         // Add the table to the UI and active set
        //         activeTableNumbers.add(tableNumber);
        //         const newTableCard = createTableCard(tableNumber, numPeople);
        //         tableGridContainer.appendChild(newTableCard);
        //         alert(`Mesa ${tableNumber} creada con ${numPeople} personas.`);
        //     } else {
        //         alert(`Error al crear mesa: ${data.message}`);
        //     }
        // })
        // .catch(error => {
        //     console.error('Error creating table:', error);
        //     alert('Ocurrió un error al intentar crear la mesa.');
        // });
        // --- End of backend simulation ---

        // For now, we simulate success directly:
        console.log(`Table ${tableNumber} created with ${numPeople} people.`);
        window.appAlert(`Mesa ${tableNumber} creada con ${numPeople} personas.`);

        // Add the table number to the set of active tables
        activeTableNumbers.add(tableNumber);

        // Create and append the table card to the grid
        const newTableCard = createTableCard(tableNumber, numPeople);
        tableGridContainer.appendChild(newTableCard);

        createTableModal.style.display = 'none'; // Close the modal
        createTableForm.reset(); // Clear the form
    });

    // Placeholder for future functionality:
    // - To "remove" a table (e.g., when the bill is paid), you'd find its card
    //   in the DOM and remove it from `tableGridContainer`, and also delete
    //   its number from `activeTableNumbers`. This would also involve a backend call.
    // - "Each waiter only sees their tables" would require a backend to filter
    //   the tables returned to the frontend based on the logged-in waiter's ID.
});
