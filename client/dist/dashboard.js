function dashboardWelcomeQuicklinksSetupInputAndFilter () {
  const targetSpan = document.querySelector(
    '.cms-content-header-info .breadcrumbs-wrapper'
  )
  const wrapper = document.querySelector('.DashboardWelcomeQuicklinks')

  const inputBox = document.createElement('input')
  inputBox.type = 'text'
  inputBox.placeholder = 'Type to filter quick-links...'
  inputBox.classList.add('no-change-track', 'quick-links-filter')

  targetSpan.appendChild(inputBox)
  targetSpan.style.display = 'flex'
  targetSpan.style.justifyContent = 'space-between'

  function filterGridCells () {
    const words = inputBox.value
      .trim()
      .toLowerCase()
      .split(/\s+/)
      .filter(Boolean)
    const gridCells = document.querySelectorAll('div.grid-cell')

    if (words.length === 0) {
      wrapper.classList.remove('is-filtered')
      gridCells.forEach(cell => {
        cell.style.display = ''
        cell.querySelectorAll('.entries h2').forEach(entry => {
          entry.style.display = ''
        })
      })
      return
    }

    wrapper.classList.add('is-filtered')

    gridCells.forEach(cell => {
      const heading = cell.querySelector('.header h1')
      const headingText = heading
        ? heading.textContent.trim().toLowerCase()
        : ''
      const headingMatches = words.every(word => headingText.includes(word))

      if (headingMatches) {
        cell.style.display = ''
        cell.querySelectorAll('.entries h2').forEach(entry => {
          entry.style.display = ''
        })
        return
      }

      const entries = cell.querySelectorAll('.entries h2')
      let anyVisible = false

      entries.forEach(entry => {
        // Skip the "… »" toggle button
        if (entry.classList.contains('more-item-more')) return
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
