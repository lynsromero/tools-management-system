import { Link } from 'react-router-dom';

function TypeBadge({ type }) {
    const classes =
        type === 'extension'
            ? 'bg-indigo-50 text-indigo-700 ring-indigo-600/20'
            : 'bg-violet-50 text-violet-700 ring-violet-600/20';

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset ${classes}`}>
            {type}
        </span>
    );
}

function StatusBadge({ isActive }) {
    return (
        <span className={`inline-flex items-center gap-1.5 text-xs font-medium ${isActive ? 'text-green-700' : 'text-slate-500'}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${isActive ? 'bg-green-500' : 'bg-slate-300'}`}></span>
            {isActive ? 'Active' : 'Inactive'}
        </span>
    );
}

export default function ToolTable({ tools, onDelete }) {
    const readOnly = !onDelete;

    if (tools.length === 0) {
        return (
            <div className="rounded-xl border-2 border-dashed border-slate-200 bg-white p-16 text-center">
                <p className="text-sm font-medium text-slate-400">
                    {readOnly
                        ? 'No tools available right now.'
                        : 'No tools yet. Click “New tool” to create your first one.'}
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200">
                <thead className="bg-slate-50">
                    <tr>
                        <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Tool</th>
                        <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Type</th>
                        <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Pricing</th>
                        <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Price</th>
                        <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Devices</th>
                        <th className="px-5 py-3 text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">Status</th>
                        {!readOnly && (
                            <th className="px-5 py-3 text-right text-xs font-semibold tracking-wide text-slate-500 uppercase">Actions</th>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {tools.map((tool) => (
                        <tr key={tool.id} className="transition hover:bg-slate-50">
                            <td className="px-5 py-3.5">
                                {readOnly ? (
                                    <p className="text-sm font-medium text-slate-900">{tool.name}</p>
                                ) : (
                                    <Link to={`/tools/${tool.id}`} className="text-sm font-medium text-slate-900 hover:text-indigo-600">
                                        {tool.name}
                                    </Link>
                                )}
                                <p className="text-xs text-slate-500">/{tool.slug}</p>
                            </td>
                            <td className="px-5 py-3.5"><TypeBadge type={tool.type} /></td>
                            <td className="px-5 py-3.5 text-sm text-slate-600">{tool.pricing_model}</td>
                            <td className="px-5 py-3.5 text-sm font-medium text-slate-900">${Number(tool.price).toFixed(2)}</td>
                            <td className="px-5 py-3.5 text-sm text-slate-600">{tool.device_limit}</td>
                            <td className="px-5 py-3.5"><StatusBadge isActive={tool.is_active} /></td>
                            {!readOnly && (
                                <td className="px-5 py-3.5 text-right">
                                    <button
                                        type="button"
                                        onClick={() => onDelete(tool.id)}
                                        className="text-sm font-medium text-red-600 hover:text-red-700"
                                    >
                                        Delete
                                    </button>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}