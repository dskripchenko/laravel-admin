// canvas.jsx — Design canvas frames (sidebar variants, filter variants, empty states, scaled snapshots)

function CanvasView() {
  return (
    <div className="canvas">
      <div className="canvas__sect-h">Sidebar variants</div>
      <div className="canvas__row">
        {['grouped', 'flat', 'zebra', 'iconish'].map(v => (
          <div className="frame" key={v} style={{ width: 240, height: 520 }}>
            <div className="frame__lbl">{v}</div>
            <Sidebar active="articles" collapsed={false} variant={v} />
          </div>
        ))}
        <div className="frame" style={{ width: 56, height: 520 }}>
          <div className="frame__lbl">collapsed</div>
          <div className="shell" data-collapsed="true" style={{ display: 'block' }}>
            <Sidebar active="articles" collapsed={true} variant="grouped" />
          </div>
        </div>
      </div>

      <div className="canvas__sect-h">Filter bar variants</div>
      <div className="canvas__row" style={{ flexDirection: 'column' }}>
        {['bar', 'chips', 'panel'].map(v => (
          <div className="frame" key={v} style={{ width: '100%' }}>
            <div className="frame__lbl">{v}</div>
            <FiltersBar variant={v} groupBy={false} onToggleGroupBy={() => {}} />
          </div>
        ))}
      </div>

      <div className="canvas__sect-h">Empty / Loading / Error states</div>
      <div className="canvas__row">
        <div className="frame" style={{ width: 480 }}>
          <div className="frame__lbl">empty</div>
          <EmptyState />
        </div>
        <div className="frame" style={{ width: 480 }}>
          <div className="frame__lbl">loading</div>
          <LoadingState />
        </div>
        <div className="frame" style={{ width: 480 }}>
          <div className="frame__lbl">error</div>
          <ErrorState />
        </div>
      </div>

      <div className="canvas__sect-h">Auth · Login + 2FA</div>
      <div className="canvas__row">
        <div className="frame" style={{ width: 520, height: 560 }}>
          <div className="frame__lbl">Login</div>
          <div style={{ height: '100%', overflow: 'hidden' }}><LoginScreen /></div>
        </div>
        <div className="frame" style={{ width: 520, height: 560 }}>
          <div className="frame__lbl">2FA Challenge</div>
          <div style={{ height: '100%', overflow: 'hidden' }}><TwoFactor /></div>
        </div>
      </div>

      <div className="canvas__sect-h">Toasts (info / success / warning / error)</div>
      <div className="canvas__row">
        <Toast kind="info" title="Сохранено как черновик" body="Можно опубликовать в любой момент." />
        <Toast kind="success" title="Импорт завершён" body="Загружено 247 статей. 3 ошибки — посмотреть отчёт." />
        <Toast kind="warning" title="Запланированная публикация" body="Через 1 час будет опубликована статья «Vue 3»." />
        <Toast kind="error" title="Не удалось опубликовать" body="A-1271 — отсутствует обложка." />
      </div>

      <div className="canvas__sect-h">Confirmation modal</div>
      <div className="canvas__row">
        <div className="frame" style={{ width: 520 }}>
          <div className="modal" style={{ width: '100%', boxShadow: 'none', border: 0, animation: 'none' }}>
            <div className="card__hd"><div className="card__hd-title">Удалить 3 статьи?</div></div>
            <div style={{ padding: 16 }}>
              <p className="text-sm" style={{ marginTop: 0 }}>Это действие нельзя отменить. Все связанные комментарии и метрики будут удалены.</p>
              <Field label={<span>Введите <span className="mono">DELETE</span> чтобы подтвердить</span>}>
                <Input placeholder="DELETE" />
              </Field>
            </div>
            <div className="hstack" style={{ padding: '12px 16px', borderTop: '1px solid var(--uid-border-subtle)', justifyContent: 'flex-end', gap: 8 }}>
              <Btn variant="ghost">Отмена</Btn>
              <Btn variant="primary" danger style={{ background: 'var(--uid-danger)', borderColor: 'var(--uid-danger)', color: 'white' }}>Удалить</Btn>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { CanvasView });
