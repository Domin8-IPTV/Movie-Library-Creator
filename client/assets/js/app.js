"use strict";

document.addEventListener("DOMContentLoaded", function(event) {
	var movies = [];
	const IMAGE_POSTER_FORMAT = 'w342';
	const IMAGE_BACKDROP_FORMAT = 'w1280';

	for (var movie in Data.movies) {
		if (Data.movies.hasOwnProperty(movie)) {
			var movieData = {};

			// Meta data
			movieData['id'] = Data.movies[movie].meta['id'];
			movieData['poster_path'] = ImageBaseUrl + IMAGE_POSTER_FORMAT + Data.movies[movie].meta['poster_path'];
			movieData['title'] = Data.movies[movie].meta['title'];
			movieData['overview'] = Data.movies[movie].meta['overview'];
			movieData['vote_count'] = Data.movies[movie].meta['vote_count'];
			movieData['vote_average'] = Data.movies[movie].meta['vote_average'];

			// Resolution
			var resolution;
			var height = Math.round(Number(Data.movies[movie].height));
			if (height > 2100)
				resolution = '4K';
			else if (height > 1000)
				resolution = '1080p';
			else if (height > 600)
				resolution = '720p';
			else
				resolution = 'SD';
			movieData['resolution'] = resolution;

			// Languages
			var languages = [];
			for (var i = 0; i < Data.movies[movie].audio.length; i++) {
				var language = Data.movies[movie].audio[i].language;
				if (!language)
					continue;

				language = language.trim().toLowerCase();

				if (language === 'deu')
					language = 'ger'

				languages.push(language);
			}
			movieData['languages'] = languages.join(' ');

			movies.push(movieData);
		}
	}

	var dataList = new List('movie-list', {
		valueNames: [
			{
				data: [
					'id',
					'languages',
					'vote_count',
					'vote_average',
					'resolution'
				]
			},
			'title',
			'overview',
			{
				name: 'poster_path',
				attr: 'src'
			}
		],
		item: '<li class="media">' +
				'<div class="media-left">' +
					'<img class="media-object poster_path">' +
				'</div>' +
				'<div class="media-body">' +
					'<h4 class="media-heading title"></h4>' +
					'<p class="overview"></p>' +
				'</div>' +
			'</li>'
	}, movies);

	function reload()
	{
		var items = document.getElementsByClassName('media');

		for (var i = 0; i < items.length; i++) {
			var item = items[i];
			var headline = item.querySelector('.title');
			var body = item.querySelector('.overview');

			// Votes
			var voteCount = Number(item.dataset.vote_count);
			var voteAverage = Math.round(Number(item.dataset.vote_average));

			var starHeadline = ' <span title="' + voteAverage + '/10 (' + voteCount + ')">';
			for (var stars = 0; stars < voteAverage; stars++) {
				starHeadline += '<i class="fa fa-star" aria-hidden="true"></i>';
			}
			for (var stars = voteAverage; stars < 10; stars++) {
				starHeadline += '<i class="fa fa-star-o" aria-hidden="true"></i>';
			}
			starHeadline += '</span>';
			headline.insertAdjacentHTML('beforeend', starHeadline);

			// Resolution
			var resolution = item.dataset.resolution;
			if (resolution == '4K')
				resolution = '<span class="label label-primary">4K</span>';
			else if (resolution == '1080p')
				resolution = '<span class="label label-success">1080p</span>';
			else if (resolution == '720p')
				resolution = '<span class="label label-warning">720p</span>';
			else
				resolution = '<span class="label label-danger">SD</span>';
			headline.insertAdjacentHTML('beforeend', ' ' + resolution);

			// Languages
			var languages = item.dataset.languages.split(' ').filter(function(language) {
				return language.length > 0;
			}).map(function(language) {
				if (language === 'eng')
					return 'English';
				else if (language === 'ger')
					return 'Deutsch';
				else
					return language;
			});
			if (languages.length > 0) {
				var languageList = '<h5>Languages</h5><ul>';
				for (var language = 0; language < languages.length; language++)
					languageList += '<li>' + languages[language] + '</li>';
				languageList += '</ul>';
				body.insertAdjacentHTML('beforeend', languageList);
			}
		}
	}

	dataList.on('updated', function() {
		console.log('updated');
		//reload();
	});

	reload();
});
