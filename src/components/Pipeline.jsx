import { useEffect, useState } from 'react'
import { useApi } from '../hooks/useApi'

export default function Pipeline({ site }) {
  const { get, patch, post, loading } = useApi(site.id)
  const [kanban, setKanban]   = useState([])
  const [addingTo, setAddingTo] = useState(null)
  const [editDeal, setEditDeal] = useState(null)
  const cur = site.currency || '₪'

  const load = () => {
    get('/deals/kanban').then(r => setKanban(r?.data || [])).catch(() => {})
  }

  useEffect(() => { load() }, [site.id])

  const markWon  = async (id) => { if (!confirm('לסמן כזכייה?')) return; await patch(`/deals/${id}`, { status: 'won' }); load() }
  const markLost = async (id) => { const r = prompt('סיבת הפסד:'); await patch(`/deals/${id}`, { status: 'lost', lost_reason: r||'' }); load() }

  const totalOpen = kanban.reduce((s, col) => s + col.total, 0)
  const totalDeals = kanban.reduce((s, col) => s + col.deals.length, 0)

  return (
    <div className="p-6 space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-xl font-bold text-slate-800">Pipeline מכירות</h2>
          <p className="text-sm text-slate-500 mt-0.5">{totalDeals} עסקאות פתוחות · ערך: {cur}{totalOpen.toLocaleString()}</p>
        </div>
        {loading && <span className="text-xs text-slate-400 animate-pulse">טוען...</span>}
      </div>

      {/* Kanban board */}
      <div className="flex gap-4 overflow-x-auto pb-4" style={{ minHeight: '60vh' }}>
        {kanban.map(col => (
          <KanbanCol
            key={col.stage.id}
            col={col}
            cur={cur}
            onWon={markWon}
            onLost={markLost}
            onAdd={() => setAddingTo(col.stage.id)}
            onEdit={setEditDeal}
          />
        ))}
        {!kanban.length && !loading && (
          <div className="flex-1 flex items-center justify-center text-slate-400">אין נתוני pipeline</div>
        )}
      </div>

      {/* Add deal modal */}
      {addingTo && (
        <AddDealModal
          stageId={addingTo}
          site={site}
          onClose={() => setAddingTo(null)}
          onSave={async (data) => { await post('/deals', { ...data, stage_id: addingTo }); setAddingTo(null); load() }}
        />
      )}

      {/* Edit deal modal */}
      {editDeal && (
        <EditDealModal
          deal={editDeal}
          kanban={kanban}
          cur={cur}
          onClose={() => setEditDeal(null)}
          onSave={async (data) => { await patch(`/deals/${editDeal.id}`, data); setEditDeal(null); load() }}
        />
      )}
    </div>
  )
}

function KanbanCol({ col, cur, onWon, onLost, onAdd, onEdit }) {
  const { stage, deals, total } = col
  return (
    <div className="flex-shrink-0 w-64 bg-slate-100 rounded-xl p-3">
      {/* Column header */}
      <div className="flex items-center justify-between mb-3 pb-2" style={{ borderBottom: `3px solid ${stage.color}` }}>
        <span className="font-bold text-sm text-slate-800">{stage.name}</span>
        <span className="text-xs px-2 py-0.5 rounded-full font-semibold" style={{ background: stage.color+'22', color: stage.color }}>
          {deals.length}
        </span>
      </div>

      {/* Total value */}
      {total > 0 && (
        <div className="text-xs text-slate-500 mb-2 font-medium">{cur}{total.toLocaleString()}</div>
      )}

      {/* Deal cards */}
      <div className="space-y-2">
        {deals.map(d => (
          <div key={d.id} onClick={() => onEdit(d)}
            className="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition cursor-pointer border border-slate-200">
            <div className="font-semibold text-sm text-slate-800 mb-1">{d.name}</div>
            <div className="text-lg font-bold" style={{ color: stage.color }}>{cur}{Number(d.amount||0).toLocaleString()}</div>
            {d.close_date && (
              <div className="text-xs text-slate-400 mt-1">
                📅 {new Date(d.close_date).toLocaleDateString('he-IL')}
              </div>
            )}
            <div className="flex gap-1.5 mt-2" onClick={e => e.stopPropagation()}>
              <button onClick={() => onWon(d.id)}
                className="flex-1 bg-green-50 hover:bg-green-100 text-green-700 text-xs py-1 rounded-lg font-medium transition">✓ זכייה</button>
              <button onClick={() => onLost(d.id)}
                className="flex-1 bg-red-50 hover:bg-red-100 text-red-700 text-xs py-1 rounded-lg font-medium transition">✕ הפסד</button>
            </div>
          </div>
        ))}
      </div>

      {/* Add button */}
      <button onClick={onAdd}
        className="w-full mt-2 py-2 text-slate-400 hover:text-slate-600 hover:bg-white text-sm rounded-lg transition border-2 border-dashed border-slate-300 hover:border-slate-400">
        + עסקה
      </button>
    </div>
  )
}

function AddDealModal({ site, stageId, onClose, onSave }) {
  const { get } = useApi(site.id)
  const [form, setForm] = useState({ name:'', amount:'', close_date:'', notes:'', customer_id:'' })
  const [customers, setCusts] = useState([])
  const cur = site.currency || '₪'

  useEffect(() => {
    get('/customers?limit=100').then(r => setCusts(r?.data || [])).catch(() => {})
  }, [])

  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  return (
    <Modal title="עסקה חדשה" onClose={onClose}
      footer={<><button onClick={onClose} className="btn-outline">ביטול</button><button onClick={() => onSave(form)} className="btn-primary">צור עסקה</button></>}
    >
      <Field label="שם עסקה *"><input className="input" value={form.name} onChange={e => set('name', e.target.value)} placeholder="לדוגמה: פרויקט X"/></Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label={`סכום (${cur})`}><input className="input" type="number" value={form.amount} onChange={e => set('amount', e.target.value)}/></Field>
        <Field label="תאריך סגירה"><input className="input" type="date" value={form.close_date} onChange={e => set('close_date', e.target.value)}/></Field>
      </div>
      <Field label="לקוח">
        <select className="input" value={form.customer_id} onChange={e => set('customer_id', e.target.value)}>
          <option value="">ללא לקוח</option>
          {customers.map(c => <option key={c.id} value={c.id}>{[c.first_name, c.last_name].filter(Boolean).join(' ') || c.company}</option>)}
        </select>
      </Field>
      <Field label="הערות"><textarea className="input h-16 resize-none" value={form.notes} onChange={e => set('notes', e.target.value)}/></Field>
    </Modal>
  )
}

function EditDealModal({ deal, kanban, cur, onClose, onSave }) {
  const [form, setForm] = useState({ name: deal.name, amount: deal.amount, stage_id: deal.stage_id, close_date: deal.close_date||'', notes: deal.notes||'' })
  const set = (k, v) => setForm(f => ({ ...f, [k]: v }))

  return (
    <Modal title="עריכת עסקה" onClose={onClose}
      footer={<><button onClick={onClose} className="btn-outline">ביטול</button><button onClick={() => onSave(form)} className="btn-primary">שמור</button></>}
    >
      <Field label="שם עסקה"><input className="input" value={form.name} onChange={e => set('name', e.target.value)}/></Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label={`סכום (${cur})`}><input className="input" type="number" value={form.amount} onChange={e => set('amount', e.target.value)}/></Field>
        <Field label="תאריך סגירה"><input className="input" type="date" value={form.close_date} onChange={e => set('close_date', e.target.value)}/></Field>
      </div>
      <Field label="שלב">
        <select className="input" value={form.stage_id} onChange={e => set('stage_id', e.target.value)}>
          {kanban.map(c => <option key={c.stage.id} value={c.stage.id}>{c.stage.name}</option>)}
        </select>
      </Field>
      <Field label="הערות"><textarea className="input h-16 resize-none" value={form.notes} onChange={e => set('notes', e.target.value)}/></Field>
    </Modal>
  )
}

function Modal({ title, children, footer, onClose }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center" dir="rtl">
      <div className="absolute inset-0 bg-black/50" onClick={onClose}/>
      <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6 z-10">
        <div className="flex items-center justify-between mb-5">
          <h2 className="text-lg font-bold text-slate-800">{title}</h2>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div className="space-y-3">{children}</div>
        <div className="flex gap-3 justify-end mt-5">{footer}</div>
      </div>
    </div>
  )
}

function Field({ label, children }) {
  return <div><label className="block text-sm font-medium text-slate-600 mb-1">{label}</label>{children}</div>
}
