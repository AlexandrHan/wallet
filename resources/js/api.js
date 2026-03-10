window.api = {

  async wallets() {
    const r = await fetch('/api/wallets');
    return r.json();
  },

  async walletEntries(id) {
    const r = await fetch(`/api/wallets/${id}/entries`);
    return r.json();
  },

  async banks() {
    const [a,b,c] = await Promise.all([
      fetch('/api/bank/accounts'),
      fetch('/api/bank/accounts-sggroup'),
      fetch('/api/bank/accounts-monobank')
    ]);

    return [
      ...(a.ok ? await a.json() : []),
      ...(b.ok ? await b.json() : []),
      ...(c.ok ? await c.json() : [])
    ];
  }

};
