// app.jsx — Top-level app: routing/screen switcher + Tweaks integration

const { useState: _us, useEffect: _ue } = React;

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [notifOpen, setNotifOpen] = _us(false);
  const [showToast, setShowToast] = _us(null);

  // Apply theme + density + brand color globally
  _ue(() => {
    document.documentElement.setAttribute('data-theme', t.dark ? 'dark' : 'light');
    document.documentElement.setAttribute('data-density', t.density);
    if (t.brandColor && t.brandColor !== '#14b8a6') {
      document.documentElement.style.setProperty('--uid-accent', t.brandColor);
      document.documentElement.style.setProperty('--uid-accent-hover', t.brandColor);
    } else {
      document.documentElement.style.removeProperty('--uid-accent');
      document.documentElement.style.removeProperty('--uid-accent-hover');
    }
  }, [t.dark, t.density, t.brandColor]);

  // Polling toast
  _ue(() => {
    if (!t.polling || t.view !== 'prototype' || t.screen !== 'list' || t.listState !== 'ideal') return;
    const t1 = setTimeout(() => {
      setShowToast({ kind: 'info', title: 'Новая запись', body: 'Дмитрий Орлов отправил статью на review.' });
      setTimeout(() => setShowToast(null), 4500);
    }, 4000);
    return () => clearTimeout(t1);
  }, [t.polling, t.view, t.screen, t.listState]);

  // Re-init lucide after each render
  _ue(() => {
    if (window.lucide && window.lucide.createIcons) {
      try { window.lucide.createIcons(); } catch(e) {}
    }
  });

  const tweaksUI = (
    <TweaksPanel>
      <TweakSection label="View" />
      <TweakRadio label="Mode" value={t.view} options={[{value:'prototype',label:'Prototype'},{value:'canvas',label:'Canvas'},{value:'gallery',label:'Fields'}]} onChange={v => setTweak('view', v)} />
      {t.view === 'prototype' && (
        <TweakSelect label="Screen" value={t.screen}
          options={[
            {value:'list', label:'Resource List'},
            {value:'form', label:'Resource Form'},
            {value:'view', label:'Resource View'},
            {value:'dashboard', label:'Dashboard'},
            {value:'profile', label:'Profile'},
            {value:'import', label:'Import Wizard'},
          ]}
          onChange={v => setTweak('screen', v)} />
      )}

      <TweakSection label="Theme" />
      <TweakToggle label="Dark mode" value={t.dark} onChange={v => setTweak('dark', v)} />
      <TweakColor label="Brand" value={t.brandColor} onChange={v => setTweak('brandColor', v)} />
      <TweakRadio label="Density" value={t.density} options={['comfortable','compact']} onChange={v => setTweak('density', v)} />

      <TweakSection label="Shell" />
      <TweakToggle label="Sidebar collapsed" value={t.sidebarCollapsed} onChange={v => setTweak('sidebarCollapsed', v)} />
      <TweakSelect label="Sidebar variant" value={t.sidebarVariant} options={['grouped','flat','zebra','iconish']} onChange={v => setTweak('sidebarVariant', v)} />
      <TweakToggle label="Impersonation banner" value={t.impersonation} onChange={v => setTweak('impersonation', v)} />

      {t.view === 'prototype' && t.screen === 'list' && (<>
        <TweakSection label="Resource List" />
        <TweakSelect label="Filter style" value={t.filterVariant} options={[{value:'bar',label:'Bar (chips inline)'},{value:'chips',label:'Active chips'},{value:'panel',label:'Panel (rows)'}]} onChange={v => setTweak('filterVariant', v)} />
        <TweakSelect label="State" value={t.listState} options={[{value:'ideal',label:'Ideal'},{value:'loading',label:'Loading'},{value:'empty',label:'Empty'},{value:'error',label:'Error'}]} onChange={v => setTweak('listState', v)} />
        <TweakToggle label="Bulk-mode (3 selected)" value={t.bulkMode} onChange={v => setTweak('bulkMode', v)} />
        <TweakToggle label="Polling indicator" value={t.polling} onChange={v => setTweak('polling', v)} />
      </>)}

      <TweakSection label="Quick" />
      <TweakButton label="Open notifications" onClick={() => setNotifOpen(true)} />
    </TweaksPanel>
  );

  // Auth screens are full-page (no shell)
  if (t.view === 'canvas') {
    return <>{tweaksUI}<CanvasView /></>;
  }
  if (t.view === 'gallery') {
    return (
      <>{tweaksUI}<ShellWrapper t={t} onOpenNotif={() => setNotifOpen(true)}>
        <FieldGallery />
      </ShellWrapper>
      {notifOpen && <NotificationDrawer onClose={() => setNotifOpen(false)} />}</>
    );
  }

  // Prototype mode
  let screen = null;
  let crumbs = ['Admin'];
  if (t.screen === 'list') { screen = <ResourceList density={t.density} polling={t.polling} listState={t.listState} filterVariant={t.filterVariant} bulkMode={t.bulkMode} onOpenForm={() => setTweak('screen','form')} onOpenView={() => setTweak('screen','view')} />; crumbs = ['Контент','Articles']; }
  else if (t.screen === 'form') { screen = <ResourceForm density={t.density} />; crumbs = ['Контент','Articles','Введение в Laravel 12']; }
  else if (t.screen === 'view') { screen = <ResourceView />; crumbs = ['Контент','Articles','Введение в Laravel 12']; }
  else if (t.screen === 'dashboard') { screen = <Dashboard />; crumbs = ['Аналитика','Dashboard']; }
  else if (t.screen === 'profile') { screen = <Profile />; crumbs = ['Profile']; }
  else if (t.screen === 'import') { screen = <ImportWizard />; crumbs = ['Контент','Articles','Импорт']; }

  return (
    <>
      {tweaksUI}
      <ShellWrapper t={t} crumbs={crumbs} setTweak={setTweak} onOpenNotif={() => setNotifOpen(true)}>
        {screen}
      </ShellWrapper>
      {notifOpen && <NotificationDrawer onClose={() => setNotifOpen(false)} />}
      {showToast && (
        <div className="toast-stack">
          <Toast {...showToast} />
        </div>
      )}
    </>
  );
}

function ShellWrapper({ t, children, crumbs, setTweak, onOpenNotif }) {
  return (
    <div className="shell" data-collapsed={t.sidebarCollapsed} data-impersonating={t.impersonation}>
      {t.impersonation && <ImpersonationBanner />}
      <div data-screen-label={`Sidebar`}>
        <Sidebar active={t.screen} collapsed={t.sidebarCollapsed} variant={t.sidebarVariant} />
      </div>
      <div style={{ minWidth: 0 }} data-screen-label={`Main · ${t.screen}`}>
        <Topbar
          dark={t.dark}
          crumbs={crumbs || []}
          onToggleSb={() => setTweak && setTweak('sidebarCollapsed', !t.sidebarCollapsed)}
          onOpenNotif={onOpenNotif}
          onToggleTheme={() => setTweak && setTweak('dark', !t.dark)}
        />
        {children}
      </div>
    </div>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
