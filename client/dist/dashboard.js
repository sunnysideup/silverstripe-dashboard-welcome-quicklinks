function dashboardWelcomeQuicklinksSetupInputAndFilter () {
  const targetSpan = document.querySelector(
    '.cms-content-header-info .breadcrumbs-wrapper'
  )

  const inputBox = document.createElement('input')
  inputBox.type = 'text'
  inputBox.placeholder = 'Type to filter quick-links...'
  inputBox.classList.add('no-change-track', 'quick-links-filter')

  targetSpan.appendChild(inputBox)
  targetSpan.style.display = 'flex'
  targetSpan.style.justifyContent = 'space-between'

  const originalDisplayState = new Map()
  document.querySelectorAll('div.grid-cell .entries h2').forEach(entry => {
    originalDisplayState.set(entry, entry.style.display)
  })

  function filterGridCells () {
    const words = inputBox.value
      .trim()
      .toLowerCase()
      .split(/\s+/)
      .filter(Boolean)
    const gridCells = document.querySelectorAll('div.grid-cell')

    if (words.length === 0) {
      gridCells.forEach(cell => {
        cell.style.display = ''
      })
      originalDisplayState.forEach((display, entry) => {
        entry.style.display = display
      })
      return
    }

    gridCells.forEach(cell => {
      const entries = cell.querySelectorAll('.entries h2')
      let anyVisible = false

      entries.forEach(entry => {
        const text = entry.textContent.trim().toLowerCase()
        const matches = words.every(word => text.includes(word))
        entry.style.display = matches ? '' : 'none'
        if (matches) anyVisible = true
      })

      cell.style.display = anyVisible ? '' : 'none'
    })
  }

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

  link.innerHTML = areHidden ? '… &laquo; ' : '… &raquo;'
}
