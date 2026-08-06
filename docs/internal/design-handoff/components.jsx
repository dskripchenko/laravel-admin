// components.jsx — primitive UI building blocks (icons, badges, etc.)
// All components are exposed on window for cross-script access.

const { useState, useEffect, useRef, useMemo, useCallback } = React;

/* ---------- Icon: Lucide via CDN ---------- */
function Icon({ name, size = 16, stroke = 2, className = '', style = {} }) {
  const ref = useRef(null);
  useEffect(() => {
    if (!ref.current || !window.lucide) return;
    ref.current.innerHTML = '';
    const icons = window.lucide.icons || window.lucide;
    const node = window.lucide.createElement
      ? window.lucide.createElement(icons[toPascal(name)] || icons.Circle)
      : null;
    if (node) {
      node.setAttribute('width', size);
      node.setAttribute('height', size);
      node.setAttribute('stroke-width', stroke);
      ref.current.appendChild(node);
    } else {
      // fallback: use createIcons api
      ref.current.setAttribute('data-lucide', name);
      ref.current.setAttribute('width', size);
      ref.current.setAttribute('height', size);
      ref.current.setAttribute('stroke-width', stroke);
      window.lucide.createIcons({ nameAttr: 'data-lucide', attrs: { width: size, height: size, 'stroke-width': stroke } });
    }
  }, [name, size, stroke]);
  return <span ref={ref} className={`iconbox ${className}`} style={{ width: size, height: size, ...style }} aria-hidden="true" />;
}
function toPascal(s) {
  return s.split('-').map(p => p[0].toUpperCase() + p.slice(1)).join('');
}

/* ---------- Btn ---------- */
function Btn({ children, variant = 'default', size = 'md', icon, iconRight, block, danger, onClick, disabled, ...rest }) {
  const cls = ['btn'];
  if (variant === 'primary') cls.push('btn--primary');
  if (variant === 'ghost') cls.push('btn--ghost');
  if (danger) cls.push('btn--danger');
  if (size === 'sm') cls.push('btn--sm');
  if (size === 'lg') cls.push('btn--lg');
  if (block) cls.push('btn--block');
  if (!children && icon) cls.push('btn--icon');
  return (
    <button className={cls.join(' ')} onClick={onClick} disabled={disabled} {...rest}>
      {icon && <Icon name={icon} size={14} />}
      {children}
      {iconRight && <Icon name={iconRight} size={14} />}
    </button>
  );
}

/* ---------- Field wrappers ---------- */
function Field({ label, help, error, required, children }) {
  return (
    <div className="field">
      {label && (
        <label className="field__label">
          {label}{required && <span className="req">*</span>}
        </label>
      )}
      {children}
      {error ? <div className="field__error"><Icon name="alert-circle" size={12} /> {error}</div>
             : help ? <div className="field__help">{help}</div> : null}
    </div>
  );
}

function Input({ icon, iconRight, error, large, readOnly, ...rest }) {
  const cls = ['input'];
  if (error) cls.push('input--error');
  if (large) cls.push('input--lg');
  if (readOnly) cls.push('input--readonly');
  return (
    <div className={cls.join(' ')}>
      {icon && <Icon name={icon} size={14} className="ico" />}
      <input readOnly={readOnly} {...rest} />
      {iconRight && <Icon name={iconRight} size={14} className="ico" />}
    </div>
  );
}

function Select({ icon, value, onChange, children, ...rest }) {
  return (
    <div className="input">
      {icon && <Icon name={icon} size={14} className="ico" />}
      <select value={value} onChange={onChange} {...rest} style={{ flex: 1, border: 0, background: 'transparent', fontSize: 13, outline: 0 }}>
        {children}
      </select>
      <Icon name="chevron-down" size={14} className="ico" />
    </div>
  );
}

function Checkbox({ checked, indeterminate, onChange, label }) {
  if (label) {
    return (
      <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
        <span className="chk" data-checked={checked} data-indeterminate={indeterminate} onClick={() => onChange && onChange(!checked)} />
        {label}
      </label>
    );
  }
  return <span className="chk" data-checked={checked} data-indeterminate={indeterminate} onClick={() => onChange && onChange(!checked)} />;
}

function Switch({ on, onChange, label }) {
  if (label) {
    return (
      <label style={{ display: 'inline-flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
        <span className="switch" data-on={on} onClick={() => onChange && onChange(!on)} />
        {label}
      </label>
    );
  }
  return <span className="switch" data-on={on} onClick={() => onChange && onChange(!on)} />;
}

/* ---------- Badge ---------- */
function Badge({ children, variant = 'default', dot }) {
  const cls = ['badge'];
  if (variant !== 'default') cls.push(`badge--${variant}`);
  return <span className={cls.join(' ')}>{dot && <span className="dot" />}{children}</span>;
}

/* ---------- Avatar ---------- */
function Avatar({ name, size = 'md' }) {
  const cls = ['avatar'];
  if (size === 'sm') cls.push('avatar--sm');
  if (size === 'lg') cls.push('avatar--lg');
  const initials = (name || '?').split(' ').map(s => s[0]).slice(0, 2).join('').toUpperCase();
  return <span className={cls.join(' ')}>{initials}</span>;
}

/* ---------- Toast stack ---------- */
function Toast({ kind = 'info', title, body }) {
  const ico = { success: 'check-circle-2', info: 'info', warning: 'alert-triangle', error: 'alert-octagon' }[kind];
  return (
    <div className={`toast toast--${kind}`}>
      <Icon name={ico} size={16} className="ico" />
      <div style={{ flex: 1, minWidth: 0 }}>
        {title && <b>{title}</b>}
        {body && <div className="body">{body}</div>}
      </div>
    </div>
  );
}

/* ---------- Skeleton ---------- */
function Skel({ w = '100%', h = 14, style = {} }) {
  return <span className="skel" style={{ display: 'inline-block', width: w, height: h, ...style }} />;
}

Object.assign(window, { Icon, Btn, Field, Input, Select, Checkbox, Switch, Badge, Avatar, Toast, Skel });
