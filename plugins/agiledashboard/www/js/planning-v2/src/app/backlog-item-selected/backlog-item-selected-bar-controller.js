angular
    .module('backlog-item-selected')
    .controller('BacklogItemSelectedBarController', BacklogItemSelectedBarController);

BacklogItemSelectedBarController.$inject = [
    '$scope',
    'BacklogItemSelectedService'
];

function BacklogItemSelectedBarController(
    $scope,
    BacklogItemSelectedService
) {
    var self = this;

    _.extend(self, {
        nb_selected_backlog_items: BacklogItemSelectedService.getNumberOfSelectedBacklogItem(),
        init                     : init
    });

    self.init();

    function init() {
        $scope.$watch(function() {
            return BacklogItemSelectedService.getNumberOfSelectedBacklogItem();
        }, function(new_value, old_value) {
            self.nb_selected_backlog_items = BacklogItemSelectedService.getNumberOfSelectedBacklogItem();
        }, true);
    }
}
