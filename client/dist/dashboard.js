// Function to add the input box and set up the filtering behavior
function dashboardWelcomeQuicklinksSetupInputAndFilter () {
    // Locate the target span element
    const targetSpan = document.querySelector(
        '.cms-content-header-info .breadcrumbs-wrapper'
    )

    // Create the input box
    const inputBox = document.createElement('input')
    inputBox.type = 'text'
    inputBox.placeholder = 'Type to filter quick-links...'
    inputBox.classList.add('no-change-track')
    inputBox.classList.add('quick-links-filter')

    // Append the input box to the target span
    targetSpan.appendChild(inputBox)
    targetSpan.style.display = 'flex'
    targetSpan.style.justifyContent = 'space-between'

    // Function to filter grid cells based on input
    function filterGridCells () {
        const inputValue = inputBox.value.toLowerCase()
        const gridCells = document.querySelectorAll('div.grid-cell')

        gridCells.forEach(cell => {
            // Check if the text in the cell includes the input value
            if (
                inputValue === '' ||
                cell.textContent.toLowerCase().includes(inputValue)
            ) {
                cell.style.display = '' // Show the cell
            } else {
                cell.style.display = 'none' // Hide the cell
            }
        })
    }

    // Add event listener to the input box to filter as the user types
    inputBox.addEventListener('input', filterGridCells)
}

function dashboardWelcomeQuicklinksSetupInputAndFilterToggleMore (event) {
    event.preventDefault()
    const link = event.target
    const siblingsAll = link.parentNode.parentNode.children
    const siblings = Array.from(siblingsAll).filter(child =>
        child.classList.contains('more-item')
    )
    const areHidden = siblings.some(
        sibling => sibling.style.display === 'none' || !sibling.style.display
    )

    siblings.forEach(sibling => {
        sibling.style.display = areHidden ? 'block' : 'none'
    })

    link.innerHTML = areHidden ? '&laquo; ' : '&raquo;'
}
