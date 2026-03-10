window.api = {

  async wallets(){
    const r = await fetch('/api/wallets');
    return r.json();
  },

  async walletEntries(id){
    const r = await fetch(`/api/wallets/${id}/entries`);
    return r.json();
  },

  async bankAccounts(){
    const [r1,r2,r3,r4] = await Promise.all([
      fetch('/api/bank/accounts'),
      fetch('/api/bank/accounts-sggroup'),
      fetch('/api/bank/accounts-monobank'),
      fetch('/api/bank/accounts-privat')
    ]);

    return [
      ...(r1.ok ? await r1.json() : []),
      ...(r2.ok ? await r2.json() : []),
      ...(r3.ok ? await r3.json() : []),
      ...(r4.ok ? await r4.json() : [])
    ];
  }

};
