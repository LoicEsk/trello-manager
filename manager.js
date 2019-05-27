// Stats Trello

const https = require('https');
const request = require("request");

const config = require('./config.json');


const trelloKey = config.trelloKey;
const trelloToken  = config.trelloToken;
const idTableau = config.idTableau;
const idListToClean = config.idListToClean;

const mysql = require('mysql');
const sql = mysql.createConnection({
  host: config.bdd.host,
  user: config.bdd.user,
  password: config.bdd.password,
  database: config.bdd.database
});



// module.exports.manage = function() {
  // listes du tableaux FLUX DE PRODUCTION
  https.get('https://api.trello.com/1/boards/' + idTableau + '/lists?key=' + trelloKey + '&token=' + trelloToken, (resp) => {
    let data = '';
    resp.on('data', (chunk) => {
      data += chunk;
    });
    resp.on('end', () => {
      // console.log('\n\rListes du tableaux FLUX DE PRODUCTION :');
      // console.log(data);
      getItemsCounts( JSON.parse(data) );
      
    });
  }).on("error", (err) => {
    console.log("Error: " + err.message);
  });
  
  // Nettoyage des cartes dans DONE
  cleanDONE(idListToClean); 
// }



async function getItemsCounts( listes ) {
  // console.log(listes);
  
  // connect SQL
  sql.connect((err) => {
    if (err) throw err;
    // console.log('SQL Connecté !');
  });
  const counts = [];
  for(var i in listes) {
    // console.log('- %s (id: %s)', listes[i].name, listes[i].id);
    try {
      const countsList = await getCardsCount(listes[i].id, listes[i].name);
      // console.log(countsList);
      // enrgistrement
      for(var j in countsList) {
          registerCount(j, listes[i].id, countsList[j]);
      }
      counts.push( {
        'name': listes[i].name,
        'counts': countsList
       });
    } catch(err) {
      console.log('On a un problème : %s', err.message);
    }
  }
  // console.log(counts);

  // comptes par members
  const membersCounts = {};

  // total
  // addStatsFlux( 0, counts[0].counts['0'], counts[1].counts['0'], counts[2].counts['0'], counts[3].counts['0'], counts[4].counts['0'], counts[5].counts['0']);

  // fin de connexion SQL
  sql.end(function(err) {
    // console.log('Connexion SQL fermée :)');
  });
}

async function getCardsCount(listId, listName) {
  // listes du tableaux FLUX DE PRODUCTION
  // console.log('Count de la liste %s', listId);
  return new Promise((resolve, reject) => {
    https.get('https://api.trello.com/1/lists/' + listId + '/cards?key=' + trelloKey + '&token=' + trelloToken, (resp) => {
      let data = '';
      resp.on('data', (chunk) => {
        data += chunk;
      });
      resp.on('end', () => {
        // console.log('\n\rListes cartes de la liste %s', listName);
        // console.log(data);
        const cards = JSON.parse(data);
        const counts = {
          '0': cards.length
        };
        // console.log(cards);
        // console.log('La liste %s compte %d cartes (id: %s)', listName, cards.length, listId);
        for( const i in cards) {
          for( const j in cards[i].idMembers) {
            if(counts[cards[i].idMembers[j]]) counts[cards[i].idMembers[j]]++;
            else counts[cards[i].idMembers[j]] = 1;
          }
        }
        // console.log(counts);
        resolve(counts);
        // for(var i in colonnes) {
        //   console.log('- %s (id: %s)', cards[i].name, cards[i].id);
        // }
      });
    }).on("error", (err) => {
      console.log("Error: " + err.message);
      reject(err);
    });
  });
  
}

function addStatsFlux(memberID, todo, sprint, in_progress, in_test, done, block) {
  const values = todo + ', ' + sprint + ', ' + in_progress + ', ' + in_test + ', ' + done + ', ' + block + ', ' + memberID;
  const requete = 'INSERT INTO flux_prod (id, date, todo, sprint, in_progress, in_test, done, block, memberId) VALUES (0, NOW(), ' + values + ');'
  // console.log('Requete SQL : %s', requete);
  sql.connect();
  sql.query( requete, function (error, results, fields) {
    if (error) console.log('erreur SQL %s', error.message);
    // console.log('Resultat requete: ', results);
  });
  sql.end();
}
function registerCount(memberId, columnId, count) {
  const values = '"' + memberId + '", NOW(), "' + columnId + '", ' + count;
  const requete = 'INSERT INTO counts (id, memberId, date, columnId, count) VALUES (null, ' + values + ');'
  // console.log('Requete SQL : %s', requete);
  // return new Promise((resolve, reject) => {
    sql.query( requete, function (error, results, fields) {
      if (error) console.log('erreur SQL %s', error.message);
      // console.log('Resultat requete: ', results);
      
    });
  // });
}

// nettaoyage de la liste DONE
async function cleanDONE(idListe) {
  const cards = await trelloRequest( 'lists/' + idListe + '/cards');
  console.log( cards.length + ' carte(s) dans DONE');
  // console.log(cards);
  for( const i in cards ) {
    // console.log('id de la carte : ' + cards[i].id);
    const actions = await trelloRequest('cards/' + cards[i].id + '/actions', 'filter=updateCard');
    for(const j in actions) {
      // console.log('Action :');
      // console.log(actions[j].data.listAfter);
      // console.log('--');
      if(actions[j].data.listAfter != undefined) {
        const le = new Date( actions[j].date);
        const ilYa = (new Date(Date.now()) - le) / 86400000; // en jours
        // console.log('Fait il y a  ' + (Math.round(ilYa*10)/10) + ' jour(s) : ' + cards[i].name);
        if(ilYa > 7) { // il y a 7 jours
          closeCard(cards[i].id);
          // await trelloRequest('cards/' + cards[i].id + '/closed', 'value=true');
          console.log('La carte ' + cards[i].name + ' est archivée');
        }
        break;
      }
    }
  }

}

function trelloRequest( cmd, params ) {
  const tail = params ? '&' + params : '';
  return new Promise((resolve, reject) => {
    // console.log('Envoi de la requete : ' + 'https://api.trello.com/1/' + cmd + '?key=' + trelloKey + '&token=' + trelloToken + tail)
    https.get('https://api.trello.com/1/' + cmd + '?key=' + trelloKey + '&token=' + trelloToken + tail, (resp) => {
      let data = '';
      resp.on('data', (chunk) => {
        data += chunk;
      });
      resp.on('end', () => {
        // console.log('trelloRequest result :');
        if(!data) resolve(null);
        else {
          try{
            const dataJSON = JSON.parse(data);
            resolve(dataJSON);
          } catch(e) {
            // erreur de parsage JSON -> ça ne doit pas être du JSON ;)
            resolve(data);
          }
        }
      });
    }).on("error", (err) => {
      console.log("Error: " + err.message);
      reject(err);
    });
  });
}

function closeCard( cardID ) {

  var options = { method: 'PUT',
    url: 'https://api.trello.com/1/cards/' + cardID,
    qs: { 
      'key'     : trelloKey,
      'token'   : trelloToken,
      'closed'   : true
     } };

  request(options, function (error, response, body) {
    if (error) throw new Error(error);

    // console.log(body);
  });
}