import { useState, useEffect, useRef } from 'react';
import { FileText, Printer, Download, X } from 'lucide-react';
import Modal from '../common/Modal';
import { parseServerDate, formatScheduleDate } from '../../utils/dates';

export default function InvoiceView({ isOpen, onClose, order }) {
  const printRef = useRef(null);

  if (!order) return null;

  // Load completion data (photos, signature) from localStorage
  const completionData = (() => {
    try {
      return JSON.parse(localStorage.getItem(`fsm_completion_${order.id}`) || '{}');
    } catch {
      return {};
    }
  })();

  // Load consumed parts from localStorage or props
  const partsUsed = (() => {
    try {
      return JSON.parse(localStorage.getItem(`fsm_parts_${order.id}`) || '[]');
    } catch {
      return [];
    }
  })();

  const laborHours = (() => {
    if (!order.started_at || !order.completed_at) return 0;
    return (parseServerDate(order.completed_at) - parseServerDate(order.started_at)) / 3600000;
  })();

  const laborRate = 85; // $/hr - would come from settings
  const laborCost = Math.round(laborHours * laborRate * 100) / 100;
  const partsCost = partsUsed.reduce((s, p) => s + (p.quantity || 0) * (p.unit_cost || 0), 0);
  const subtotal = laborCost + partsCost;
  const tax = Math.round(subtotal * 0.08 * 100) / 100; // 8% tax
  const total = subtotal + tax;

  const handlePrint = () => {
    window.print();
  };

  const invoiceNumber = `INV-${order.reference?.replace('WO-', '') || order.id}`;
  const invoiceDate = order.completed_at
    ? new Date(order.completed_at).toLocaleDateString()
    : new Date().toLocaleDateString();

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="" size="lg">
      {/* Action bar - hidden when printing */}
      <div className="flex items-center justify-between mb-6 print:hidden">
        <h2 className="text-lg font-bold text-gray-900">Invoice</h2>
        <div className="flex gap-2">
          <button
            onClick={handlePrint}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
          >
            <Printer className="h-4 w-4" />
            Print
          </button>
        </div>
      </div>

      {/* Invoice content */}
      <div ref={printRef} className="print:p-8">
        {/* Header */}
        <div className="flex items-start justify-between mb-8 pb-6 border-b border-gray-200">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">INVOICE</h1>
            <p className="text-sm text-gray-500 mt-1">{invoiceNumber}</p>
            <p className="text-sm text-gray-500">Date: {invoiceDate}</p>
          </div>
          <div className="text-right">
            <p className="font-bold text-gray-900">FSM Service Co.</p>
            <p className="text-sm text-gray-500">Field Service Management</p>
          </div>
        </div>

        {/* Customer & Work Order */}
        <div className="grid grid-cols-2 gap-8 mb-8">
          <div>
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Bill To</h3>
            <p className="font-medium text-gray-900">{order.customer?.name || 'N/A'}</p>
            {order.address?.street && (
              <p className="text-sm text-gray-600">{order.address.street}</p>
            )}
            {order.address?.city && (
              <p className="text-sm text-gray-600">
                {order.address.city}{order.address.state && `, ${order.address.state}`} {order.address.zip}
              </p>
            )}
            {order.customer?.phone && (
              <p className="text-sm text-gray-500 mt-1">{order.customer.phone}</p>
            )}
            {order.customer?.email && (
              <p className="text-sm text-gray-500">{order.customer.email}</p>
            )}
          </div>
          <div>
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Work Order</h3>
            <p className="font-medium text-gray-900">{order.reference}</p>
            <p className="text-sm text-gray-600">{order.title}</p>
            {order.scheduled_date && (
              <p className="text-sm text-gray-500 mt-1">
                Service Date: {formatScheduleDate(order.scheduled_date)}
              </p>
            )}
          </div>
        </div>

        {/* Description */}
        {order.description && (
          <div className="mb-6">
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Description of Work</h3>
            <p className="text-sm text-gray-700">{order.description}</p>
          </div>
        )}

        {/* Line items table */}
        <table className="w-full text-sm mb-8">
          <thead>
            <tr className="border-b-2 border-gray-300">
              <th className="text-left py-2 font-semibold text-gray-700">Item</th>
              <th className="text-center py-2 font-semibold text-gray-700 w-20">Qty</th>
              <th className="text-right py-2 font-semibold text-gray-700 w-24">Rate</th>
              <th className="text-right py-2 font-semibold text-gray-700 w-24">Amount</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {/* Labor */}
            {laborHours > 0 && (
              <tr>
                <td className="py-3">
                  <p className="font-medium text-gray-900">Labor</p>
                  <p className="text-xs text-gray-500">
                    {Math.floor(laborHours)}h {Math.round((laborHours % 1) * 60)}m
                  </p>
                </td>
                <td className="text-center py-3">{laborHours.toFixed(2)}</td>
                <td className="text-right py-3">${laborRate.toFixed(2)}/hr</td>
                <td className="text-right py-3 font-medium">${laborCost.toFixed(2)}</td>
              </tr>
            )}

            {/* Parts */}
            {partsUsed.map((part, i) => (
              <tr key={i}>
                <td className="py-3">
                  <p className="font-medium text-gray-900">{part.name}</p>
                  <p className="text-xs text-gray-500">SKU: {part.sku}</p>
                </td>
                <td className="text-center py-3">{part.quantity}</td>
                <td className="text-right py-3">${(part.unit_cost || 0).toFixed(2)}</td>
                <td className="text-right py-3 font-medium">
                  ${((part.quantity || 0) * (part.unit_cost || 0)).toFixed(2)}
                </td>
              </tr>
            ))}

            {laborHours === 0 && partsUsed.length === 0 && (
              <tr>
                <td className="py-3">
                  <p className="font-medium text-gray-900">Service Call</p>
                  <p className="text-xs text-gray-500">{order.title}</p>
                </td>
                <td className="text-center py-3">1</td>
                <td className="text-right py-3">—</td>
                <td className="text-right py-3 font-medium">$0.00</td>
              </tr>
            )}
          </tbody>
        </table>

        {/* Totals */}
        <div className="flex justify-end">
          <div className="w-64 space-y-2 text-sm">
            <div className="flex justify-between">
              <span className="text-gray-500">Subtotal</span>
              <span className="font-medium">${subtotal.toFixed(2)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-gray-500">Tax (8%)</span>
              <span className="font-medium">${tax.toFixed(2)}</span>
            </div>
            <div className="flex justify-between pt-2 border-t-2 border-gray-300 text-base">
              <span className="font-bold text-gray-900">Total</span>
              <span className="font-bold text-gray-900">${total.toFixed(2)}</span>
            </div>
          </div>
        </div>

        {/* Signature */}
        {completionData.signature && (
          <div className="mt-8 pt-6 border-t border-gray-200">
            <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Customer Acknowledgment</h3>
            <img
              src={completionData.signature}
              alt="Customer signature"
              className="max-h-16 border-b border-gray-300"
            />
            <p className="text-xs text-gray-500 mt-1">
              Signed {completionData.completedAt ? new Date(completionData.completedAt).toLocaleString() : ''}
            </p>
          </div>
        )}

        {/* Footer */}
        <div className="mt-8 pt-4 border-t border-gray-200 text-xs text-gray-400 text-center">
          <p>Thank you for your business!</p>
          <p className="mt-1">Generated by FSM — Field Service Management</p>
        </div>
      </div>
    </Modal>
  );
}
